<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\QuizRequest;
use App\Models\Choice;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    public function index(Request $request): Response
    {
        $quizzes = $request->user()->quizzes()
            ->withCount(['questions', 'attempts'])
            ->with('groups:id,name')
            ->latest()
            ->get()
            ->map(fn (Quiz $quiz) => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'subject' => $quiz->subject,
                'level' => $quiz->level->label(),
                'questions_count' => $quiz->questions_count,
                'attempts_count' => $quiz->attempts_count,
                'groups' => $quiz->groups->pluck('name'),
                'status' => $this->status($quiz),
                'due_at' => $quiz->due_at->toIso8601String(),
            ]);

        return Inertia::render('teacher/quizzes/Index', ['quizzes' => $quizzes]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('teacher/quizzes/Edit', [
            'quiz' => null,
            'locked' => false,
            ...$this->editorOptions($request->user()),
        ]);
    }

    public function store(QuizRequest $request): RedirectResponse
    {
        $quiz = DB::transaction(function () use ($request) {
            $quiz = $request->user()->quizzes()->create($this->quizAttributes($request, null));
            $this->saveQuestions($quiz, $request->validated('questions'));
            $quiz->groups()->sync($request->validated('group_ids', []));

            return $quiz;
        });

        return to_route('teacher.quizzes.index')->with('success', $quiz->published_at
            ? "Quiz « {$quiz->title} » publié pour ".count($request->validated('group_ids', [])).' groupe(s).'
            : "Brouillon « {$quiz->title} » enregistré.");
    }

    public function edit(Request $request, Quiz $quiz): Response
    {
        Gate::authorize('manage', $quiz);

        $quiz->load(['questions.choices', 'groups:id']);

        return Inertia::render('teacher/quizzes/Edit', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'subject' => $quiz->subject,
                'level' => $quiz->level->value,
                'duration_minutes' => $quiz->duration_minutes,
                'due_at' => $quiz->due_at->format('Y-m-d\TH:i'),
                'corrections_at' => $quiz->corrections_at->format('Y-m-d\TH:i'),
                'published' => $quiz->published_at !== null,
                'group_ids' => $quiz->groups->pluck('id'),
                // The owner may see the correct answers of their own quiz.
                'questions' => $quiz->questions->map(fn (Question $q) => [
                    'text' => $q->text,
                    'notion_id' => $q->notion_id,
                    'choices' => $q->choices->pluck('text'),
                    'correct' => $q->choices->search(fn (Choice $c) => $c->is_correct),
                ]),
            ],
            'locked' => $quiz->attempts()->exists(),
            'attemptsCount' => $quiz->attempts()->count(),
            ...$this->editorOptions($request->user()),
        ]);
    }

    public function update(QuizRequest $request, Quiz $quiz): RedirectResponse
    {
        Gate::authorize('manage', $quiz);

        DB::transaction(function () use ($request, $quiz) {
            $quiz->update($this->quizAttributes($request, $quiz));
            if (! $request->questionsLocked()) {
                $quiz->questions()->delete(); // choices cascade
                $this->saveQuestions($quiz, $request->validated('questions'));
            }
            $quiz->groups()->sync($request->validated('group_ids', []));
        });

        return to_route('teacher.quizzes.index')->with('success', "Quiz « {$quiz->title} » enregistré.");
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        Gate::authorize('manage', $quiz);

        // Results are never deleted behind the teacher's back.
        $attempts = $quiz->attempts()->count();
        if ($attempts > 0) {
            return back()->with('error', "Ce quiz a déjà été passé par {$attempts} étudiant(s) : il ne peut pas être supprimé, pour conserver leurs résultats.");
        }

        $quiz->delete();

        return to_route('teacher.quizzes.index')->with('success', "Quiz « {$quiz->title} » supprimé.");
    }

    /** Copy as a draft (questions, choices, notions, groups), to reuse or change a locked quiz. */
    public function duplicate(Quiz $quiz): RedirectResponse
    {
        Gate::authorize('manage', $quiz);

        $copy = DB::transaction(function () use ($quiz) {
            $copy = $quiz->replicate(['published_at']);
            $copy->title = mb_substr($quiz->title.' (copie)', 0, 255);
            $copy->save();

            foreach ($quiz->questions()->with('choices')->get() as $question) {
                $newQuestion = $copy->questions()->create($question->only(['notion_id', 'text', 'position']));
                foreach ($question->choices as $choice) {
                    $newQuestion->choices()->create($choice->only(['text', 'is_correct', 'position']));
                }
            }
            $copy->groups()->sync($quiz->groups()->pluck('groups.id'));

            return $copy;
        });

        return to_route('teacher.quizzes.edit', $copy)->with('success', 'Copie créée en brouillon : modifiez-la puis publiez-la.');
    }

    /** @return array<string, mixed> */
    private function quizAttributes(QuizRequest $request, ?Quiz $quiz): array
    {
        $data = $request->safe()->only(['title', 'description', 'subject', 'level', 'duration_minutes', 'due_at', 'corrections_at']);

        if ($request->boolean('publish')) {
            $data['published_at'] = $quiz?->published_at ?? now();
        } elseif ($quiz === null || ! $quiz->attempts()->exists()) {
            $data['published_at'] = null; // back to draft (impossible once answered)
        }

        return $data;
    }

    /** @param list<array{text: string, notion_id: int, choices: list<string>, correct: int}> $questions */
    private function saveQuestions(Quiz $quiz, array $questions): void
    {
        foreach (array_values($questions) as $position => $q) {
            $question = $quiz->questions()->create([
                'notion_id' => $q['notion_id'],
                'text' => trim($q['text']),
                'position' => $position,
            ]);
            foreach (array_values($q['choices']) as $index => $text) {
                $question->choices()->create([
                    'text' => trim($text),
                    'is_correct' => $index === (int) $q['correct'],
                    'position' => $index,
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function editorOptions(User $teacher): array
    {
        return [
            'groups' => $teacher->teachingGroups()->orderBy('name')->get(['id', 'name', 'subject', 'level'])
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'subject' => $g->subject, 'level' => $g->level->value]),
            'notions' => $teacher->notions()->orderBy('subject')->orderBy('name')->get(['id', 'name', 'subject']),
            'levels' => GroupController::levelOptions(),
        ];
    }

    private function status(Quiz $quiz): string
    {
        return match (true) {
            $quiz->published_at === null => 'draft',
            $quiz->due_at->isFuture() => 'open',
            default => 'closed',
        };
    }
}
