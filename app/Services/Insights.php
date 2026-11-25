<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Quiz;
use App\Models\User;
use App\Support\Grading;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns quiz results into the per-notion understanding view.
 * Notion success rate = correct answers on the notion / answers given on it
 * (a question left blank is not an "answer given"). Only submitted attempts count.
 */
class Insights
{
    /**
     * Rates per notion (or per student and notion) for some quizzes and students.
     *
     * @param  array<int, int>  $quizIds
     * @param  array<int, int>|null  $studentIds  null = every student
     * @return Collection<int, object{notion_id: int, name: string, student_id?: int, correct: int, answered: int, rate: float|null}>
     */
    public function notionRates(array $quizIds, ?array $studentIds = null, bool $perStudent = false): Collection
    {
        if ($quizIds === [] || $studentIds === []) {
            return collect();
        }

        return DB::table('attempt_answers as aa')
            ->join('attempts as a', 'a.id', '=', 'aa.attempt_id')
            ->join('questions as q', 'q.id', '=', 'aa.question_id')
            ->join('notions as n', 'n.id', '=', 'q.notion_id')
            ->where('a.status', AttemptStatus::Submitted->value)
            ->whereIn('a.quiz_id', $quizIds)
            ->when($studentIds !== null, fn (Builder $q) => $q->whereIn('a.student_id', $studentIds))
            ->whereNotNull('aa.choice_id')
            ->when($perStudent, fn (Builder $q) => $q->addSelect('a.student_id')->groupBy('a.student_id'))
            ->groupBy('n.id', 'n.name')
            ->addSelect('n.id as notion_id', 'n.name')
            ->selectRaw('SUM(aa.is_correct) as correct, COUNT(*) as answered')
            ->orderBy('n.name')
            ->get()
            ->map(function (object $row) {
                $row->correct = (int) $row->correct;
                $row->answered = (int) $row->answered;
                $row->rate = Grading::successRate($row->correct, $row->answered);

                return $row;
            });
    }

    /**
     * Everything the group "understanding" page needs: quizzes, notions, students
     * (active members only, rule 18) with their average, notion rates and difficulty.
     *
     * @return array{quizzes: Collection<int, array<string, mixed>>, notions: Collection<int, array<string, mixed>>, students: Collection<int, array<string, mixed>>}
     */
    public function group(Group $group): array
    {
        $students = $group->students()->orderBy('last_name')->orderBy('first_name')->get(['users.id', 'first_name', 'last_name']);
        $studentIds = $students->pluck('id')->all();

        $quizzes = Quiz::published()
            ->where('teacher_id', $group->teacher_id)
            ->whereHas('groups', fn ($q) => $q->whereKey($group->id))
            ->orderBy('due_at')
            ->get(['id', 'title', 'due_at', 'corrections_at']);
        $quizIds = $quizzes->pluck('id')->all();

        $attempts = Attempt::query()
            ->where('status', AttemptStatus::Submitted)
            ->whereIn('quiz_id', $quizIds)
            ->whereIn('student_id', $studentIds)
            ->get(['quiz_id', 'student_id', 'score']);

        $notions = $this->notionRates($quizIds, $studentIds);
        $byStudent = $this->notionRates($quizIds, $studentIds, perStudent: true)->groupBy('student_id');

        $rows = $students->map(function (User $student) use ($attempts, $byStudent, $quizIds) {
            $scores = $attempts->where('student_id', $student->id)->pluck('score')->map(fn ($s) => (float) $s);
            $average = $scores->isEmpty() ? null : round($scores->avg(), 2);
            $rates = collect($byStudent->get($student->id, []))->mapWithKeys(fn ($r) => [$r->notion_id => $r->rate]);

            return [
                'id' => $student->id,
                'name' => $student->name,
                'average' => $average,
                'done' => $scores->count(),
                'total' => count($quizIds),
                'rates' => $rates,
                'difficulty' => Grading::isInDifficulty($average, $rates->all()),
                'reasons' => $this->reasons($average, collect($byStudent->get($student->id, []))),
            ];
        })->sortBy([
            fn ($a, $b) => $b['difficulty'] <=> $a['difficulty'],
            fn ($a, $b) => ($a['average'] ?? 99) <=> ($b['average'] ?? 99),
        ])->values();

        $quizRows = $quizzes->map(function (Quiz $quiz) use ($attempts, $studentIds) {
            $scores = $attempts->where('quiz_id', $quiz->id)->pluck('score')->map(fn ($s) => (float) $s);

            return [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'due_at' => $quiz->due_at->toIso8601String(),
                'submitted' => $scores->count(),
                'expected' => count($studentIds),
                'average' => $scores->isEmpty() ? null : round($scores->avg(), 2),
            ];
        });

        return [
            'quizzes' => $quizRows->values(),
            'notions' => $notions->map(fn ($n) => [
                'id' => $n->notion_id, 'name' => $n->name, 'correct' => $n->correct, 'answered' => $n->answered, 'rate' => $n->rate,
            ])->values(),
            'students' => $rows,
        ];
    }

    /**
     * Why a student is flagged, in French: "Moyenne 8,5/20", "Boucles 33 %".
     *
     * @param  Collection<int, object>  $rates
     * @return list<string>
     */
    private function reasons(?float $average, Collection $rates): array
    {
        $reasons = [];
        if ($average !== null && $average < (float) config('focusia.difficulty.average')) {
            $reasons[] = 'Moyenne '.str_replace('.', ',', (string) $average).'/20';
        }
        foreach ($rates as $rate) {
            if ($rate->rate !== null && $rate->rate < (float) config('focusia.difficulty.notion_rate')) {
                $reasons[] = $rate->name.' '.round($rate->rate * 100).' %';
            }
        }

        return $reasons;
    }
}
