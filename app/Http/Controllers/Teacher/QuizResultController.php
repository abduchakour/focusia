<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Choice;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Insights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Results of one quiz for its teacher: participation, grades, notions, questions, students. */
class QuizResultController extends Controller
{
    public function __invoke(Request $request, Quiz $quiz, Insights $insights): Response
    {
        Gate::authorize('manage', $quiz);

        $groups = $quiz->groups()->orderBy('name')->get(['groups.id', 'name']);
        $selected = $groups->firstWhere('id', (int) $request->query('groupe'));
        $groupIds = $selected ? [$selected->id] : $groups->pluck('id')->all();

        // Every enrollment (removed ones too: their results are kept, rule 18).
        $enrollments = Enrollment::whereIn('group_id', $groupIds)->with('group:id,name')->get();
        $activeIds = $enrollments->whereNull('removed_at')->pluck('student_id')->unique();
        $memberIds = $enrollments->pluck('student_id')->unique();
        $groupNames = $enrollments->groupBy('student_id')->map(fn ($e) => $e->pluck('group.name')->unique()->values());

        $attempts = $quiz->attempts()
            ->whereIn('student_id', $memberIds)
            ->with('student:id,first_name,last_name')
            ->get();
        $submitted = $attempts->where('status', AttemptStatus::Submitted);
        $scores = $submitted->pluck('score')->map(fn ($s) => (float) $s)->sort()->values();

        $missing = $activeIds->diff($submitted->pluck('student_id'));
        $missingStudents = User::whereIn('id', $missing)->orderBy('last_name')->get(['id', 'first_name', 'last_name'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'groups' => $groupNames->get($s->id, []),
                'started' => $attempts->contains(fn (Attempt $a) => $a->student_id === $s->id),
            ]);

        return Inertia::render('teacher/quizzes/Results', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'subject' => $quiz->subject,
                'due_at' => $quiz->due_at->toIso8601String(),
                'corrections_at' => $quiz->corrections_at->toIso8601String(),
                'is_open' => $quiz->due_at->isFuture(),
            ],
            'groups' => $groups->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name]),
            'selectedGroup' => $selected?->id,
            'stats' => [
                'submitted' => $submitted->whereIn('student_id', $activeIds)->count(),
                'expected' => $activeIds->count(),
                'average' => $scores->isEmpty() ? null : round($scores->avg(), 2),
                'median' => $scores->isEmpty() ? null : round((float) $scores->median(), 2),
                'min' => $scores->min(),
                'max' => $scores->max(),
                'passed' => $scores->filter(fn ($s) => $s >= 10)->count(),
            ],
            'distribution' => $this->distribution($scores->all()),
            'notions' => $insights->notionRates([$quiz->id], $memberIds->all())->map(fn ($n) => [
                'name' => $n->name, 'correct' => $n->correct, 'answered' => $n->answered, 'rate' => $n->rate,
            ])->values(),
            'questions' => $this->questions($quiz, $submitted->pluck('id')->all()),
            'students' => $submitted->sortByDesc(fn (Attempt $a) => (float) $a->score)->map(fn (Attempt $a) => [
                'id' => $a->student_id,
                'name' => $a->student->name,
                'groups' => $groupNames->get($a->student_id, []),
                'score' => (float) $a->score,
                'correct' => $a->correct_count,
                'total' => $a->total_count,
                'answered_at' => $a->answered_at->toIso8601String(),
                'synced_late' => $a->answered_at->diffInMinutes($a->synced_at) >= 5,
                'removed' => ! $activeIds->contains($a->student_id),
            ])->values(),
            'missing' => $missingStudents,
        ]);
    }

    /**
     * Grades grouped in ranges out of 20 (the last one includes 20).
     *
     * @param  list<float>  $scores
     * @return list<array{label: string, from: int, to: int, count: int}>
     */
    private function distribution(array $scores): array
    {
        $ranges = [[0, 4], [4, 8], [8, 10], [10, 12], [12, 14], [14, 16], [16, 20]];

        return array_map(fn (array $r) => [
            'label' => "{$r[0]}–{$r[1]}",
            'from' => $r[0],
            'to' => $r[1],
            'count' => count(array_filter($scores, fn ($s) => $s >= $r[0] && ($r[1] === 20 ? $s <= 20 : $s < $r[1]))),
        ], $ranges);
    }

    /**
     * Success per question and how the choices were picked: the most frequent wrong
     * answer shows a misconception worth explaining in class.
     *
     * @param  list<int>  $attemptIds
     * @return list<array<string, mixed>>
     */
    private function questions(Quiz $quiz, array $attemptIds): array
    {
        $counts = DB::table('attempt_answers')
            ->whereIn('attempt_id', $attemptIds === [] ? [0] : $attemptIds)
            ->selectRaw('question_id, choice_id, COUNT(*) as total')
            ->groupBy('question_id', 'choice_id')
            ->get()
            ->groupBy('question_id');

        return $quiz->questions()->with(['notion:id,name', 'choices' => fn ($q) => $q->orderBy('position')])->get()
            ->map(function (Question $question) use ($counts) {
                $rows = collect($counts->get($question->id, []));
                $byChoice = $rows->whereNotNull('choice_id')->mapWithKeys(fn ($r) => [$r->choice_id => (int) $r->total]);
                $answered = $byChoice->sum();
                $correctChoice = $question->choices->firstWhere('is_correct', true);
                $correct = $byChoice->get($correctChoice?->id, 0);

                return [
                    'id' => $question->id,
                    'position' => $question->position + 1,
                    'text' => $question->text,
                    'notion' => $question->notion->name,
                    'answered' => $answered,
                    'blank' => (int) $rows->whereNull('choice_id')->sum('total'),
                    'rate' => $answered > 0 ? $correct / $answered : null,
                    'choices' => $question->choices->map(fn (Choice $c) => [
                        'id' => $c->id,
                        'text' => $c->text,
                        'is_correct' => (bool) $c->is_correct,
                        'count' => $byChoice->get($c->id, 0),
                    ])->values(),
                ];
            })->all();
    }
}
