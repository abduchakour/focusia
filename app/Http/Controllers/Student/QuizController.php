<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitAttemptRequest;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Choice;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Support\AttemptAcceptance;
use App\Support\Grading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taking a quiz. The flow is built for intermittent connections:
 *  - "start" (online) creates the attempt with the server time and sends the questions;
 *  - the student answers on the phone, offline if needed (saved in IndexedDB);
 *  - "submit" receives the answers whenever the connection comes back, grades them
 *    on the server and checks rule 8 with AttemptAcceptance.
 * The correct answers never leave the server before corrections_at (rule 7).
 */
class QuizController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();

        $quizzes = Quiz::visibleTo($student)
            ->with('teacher:id,first_name,last_name')
            ->withCount('questions')
            ->orderBy('due_at')
            ->get();

        $attempts = $student->attempts()->whereIn('quiz_id', $quizzes->pluck('id'))->get()->keyBy('quiz_id');

        return Inertia::render('student/quizzes/Index', [
            'quizzes' => $quizzes->map(fn (Quiz $quiz) => $this->card($quiz, $attempts->get($quiz->id)))->values(),
        ]);
    }

    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        Gate::authorize('take', $quiz);
        $student = $request->user();

        $attempt = $this->attemptOf($student, $quiz);
        if ($attempt?->status === AttemptStatus::Submitted) {
            return to_route('student.quizzes.result', ['quiz' => $quiz->id]);
        }
        if ($attempt === null) {
            // Rule 8 (a): the quiz must be downloaded before the due date.
            if ($quiz->due_at->isPast()) {
                return back()->with('error', 'La date limite de ce quiz est dépassée.');
            }
            // createOrFirst: two taps at once still give a single attempt (rule 3).
            $quiz->attempts()->createOrFirst(
                ['student_id' => $student->id],
                ['status' => AttemptStatus::Started, 'started_at' => now()],
            );
        }

        return to_route('student.quizzes.take', ['quiz' => $quiz->id]);
    }

    public function take(Request $request, Quiz $quiz): Response|RedirectResponse
    {
        Gate::authorize('take', $quiz);

        $attempt = $this->attemptOf($request->user(), $quiz);
        if ($attempt === null) {
            return to_route('student.quizzes.index')->with('error', 'Commencez le quiz depuis la liste.');
        }
        if ($attempt->status === AttemptStatus::Submitted) {
            return to_route('student.quizzes.result', ['quiz' => $quiz->id]);
        }

        $quiz->load('teacher:id,first_name,last_name');
        $questions = $quiz->questions()->with(['notion:id,name', 'choices' => fn ($q) => $q->orderBy('position')])->get();

        return Inertia::render('student/quizzes/Take', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'subject' => $quiz->subject,
                'description' => $quiz->description,
                'teacher' => $quiz->teacher->name,
                'duration_minutes' => $quiz->duration_minutes,
                'due_at' => $quiz->due_at->toIso8601String(),
            ],
            'attempt' => [
                'id' => $attempt->id,
                'started_at' => $attempt->started_at->toIso8601String(),
                'deadline' => $quiz->deadlineFor($attempt)->toIso8601String(),
            ],
            // Only ids and texts: `is_correct` is hidden on Choice and never selected here.
            'questions' => $questions->map(fn (Question $question) => [
                'id' => $question->id,
                'text' => $question->text,
                'notion' => $question->notion->name,
                'choices' => $question->choices->map(fn (Choice $choice) => ['id' => $choice->id, 'text' => $choice->text])->values(),
            ])->values(),
            // Lets the phone measure the gap between its clock and the server clock.
            'serverNow' => now()->getTimestampMs(),
        ]);
    }

    /** JSON endpoint called by the page and by the offline sync queue. Idempotent. */
    public function submit(SubmitAttemptRequest $request, Quiz $quiz): JsonResponse
    {
        $student = $request->user();

        return DB::transaction(function () use ($request, $quiz, $student) {
            $attempt = $quiz->attempts()->where('student_id', $student->id)->lockForUpdate()->first();

            if ($attempt === null) {
                return response()->json(['message' => "Ce quiz n'a pas été commencé."], 409);
            }
            // A retry after a lost response: send the result already stored.
            if ($attempt->status === AttemptStatus::Submitted) {
                return response()->json($this->submitted($quiz, $attempt) + ['already' => true]);
            }

            // The device clock cannot claim a moment in the future.
            $answeredAt = Carbon::parse($request->input('answered_at'))->setTimezone(config('app.timezone'))->min(now());

            $refusal = AttemptAcceptance::refusal(
                $attempt->started_at, $answeredAt, $quiz->due_at, $quiz->duration_minutes, (int) config('focusia.quiz_grace_seconds'),
            );
            if ($refusal !== null) {
                throw ValidationException::withMessages(['answered_at' => AttemptAcceptance::message($refusal)]);
            }

            $questions = $quiz->questions()->with('choices')->get()->keyBy('id');
            /** @var array<int|string, int|null> $given */
            $given = $request->input('answers', []);

            foreach ($given as $questionId => $choiceId) {
                $question = $questions->get((int) $questionId);
                if ($question === null || ($choiceId !== null && ! $question->choices->contains('id', (int) $choiceId))) {
                    throw ValidationException::withMessages(['answers' => 'Une réponse ne correspond pas à ce quiz.']);
                }
            }

            // Every question is stored; an unanswered question counts as wrong (1 point each, rule 5).
            $correct = 0;
            $rows = $questions->map(function (Question $question) use ($given, $attempt, &$correct) {
                $choiceId = $given[$question->id] ?? null;
                $isCorrect = $choiceId !== null && (bool) $question->choices->firstWhere('id', (int) $choiceId)?->is_correct;
                $correct += $isCorrect ? 1 : 0;

                return [
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'choice_id' => $choiceId === null ? null : (int) $choiceId,
                    'is_correct' => $isCorrect,
                ];
            })->values()->all();

            AttemptAnswer::insert($rows);

            $attempt->update([
                'status' => AttemptStatus::Submitted,
                'answered_at' => $answeredAt,
                'synced_at' => now(),
                'correct_count' => $correct,
                'total_count' => $questions->count(),
                'score' => Grading::quizScore($correct, $questions->count()),
            ]);

            return response()->json($this->submitted($quiz, $attempt));
        });
    }

    public function result(Request $request, Quiz $quiz): Response|RedirectResponse
    {
        Gate::authorize('take', $quiz);

        $attempt = $this->attemptOf($request->user(), $quiz);
        if ($attempt?->status !== AttemptStatus::Submitted) {
            return to_route('student.quizzes.index');
        }

        $quiz->load('teacher:id,first_name,last_name');
        $visible = $quiz->correctionsAreVisible();

        return Inertia::render('student/quizzes/Result', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'subject' => $quiz->subject,
                'teacher' => $quiz->teacher->name,
                'due_at' => $quiz->due_at->toIso8601String(),
                'corrections_at' => $quiz->corrections_at->toIso8601String(),
            ],
            'attempt' => [
                'score' => (float) $attempt->score,
                'correct' => $attempt->correct_count,
                'total' => $attempt->total_count,
                'answered_at' => $attempt->answered_at->toIso8601String(),
                'synced_at' => $attempt->synced_at->toIso8601String(),
                // Answered offline and received noticeably later (rule 8).
                'synced_late' => $attempt->answered_at->diffInMinutes($attempt->synced_at) >= 5,
            ],
            // Rule 7: nothing about which answers were right before corrections_at
            // (a per-notion count on a one-question notion would already tell).
            'corrections' => $visible ? $this->corrections($quiz, $attempt) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function card(Quiz $quiz, ?Attempt $attempt): array
    {
        $submitted = $attempt?->status === AttemptStatus::Submitted;

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'subject' => $quiz->subject,
            'teacher' => $quiz->teacher->name,
            'questions_count' => $quiz->questions_count,
            'duration_minutes' => $quiz->duration_minutes,
            'due_at' => $quiz->due_at->toIso8601String(),
            'corrections_at' => $quiz->corrections_at->toIso8601String(),
            'corrections_visible' => $quiz->correctionsAreVisible(),
            'status' => match (true) {
                $submitted => 'done',
                $quiz->due_at->isPast() => $attempt ? 'started_late' : 'missed',
                $attempt !== null => 'started',
                default => 'todo',
            },
            'score' => $submitted ? (float) $attempt->score : null,
        ];
    }

    /** @return array<string, mixed> */
    private function submitted(Quiz $quiz, Attempt $attempt): array
    {
        return [
            'score' => (float) $attempt->score,
            'correct' => $attempt->correct_count,
            'total' => $attempt->total_count,
            'result_url' => route('student.quizzes.result', ['quiz' => $quiz->id]),
        ];
    }

    /** @return array<string, mixed> */
    private function corrections(Quiz $quiz, Attempt $attempt): array
    {
        $questions = $quiz->questions()->with(['notion:id,name', 'choices' => fn ($q) => $q->orderBy('position')])->get();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $notions = $questions->groupBy('notion_id')->map(fn ($group) => [
            'name' => $group->first()->notion->name,
            'total' => $group->count(),
            'correct' => $group->filter(fn (Question $q) => (bool) $answers->get($q->id)?->is_correct)->count(),
        ])->sortBy('name')->values();

        return [
            'notions' => $notions,
            'questions' => $questions->map(fn (Question $question) => [
                'id' => $question->id,
                'text' => $question->text,
                'notion' => $question->notion->name,
                'chosen_id' => $answers->get($question->id)?->choice_id,
                'is_correct' => (bool) $answers->get($question->id)?->is_correct,
                'choices' => $question->choices->map(fn (Choice $choice) => [
                    'id' => $choice->id,
                    'text' => $choice->text,
                    'is_correct' => (bool) $choice->is_correct,
                ])->values(),
            ])->values(),
        ];
    }

    private function attemptOf(User $student, Quiz $quiz): ?Attempt
    {
        return $quiz->attempts()->where('student_id', $student->id)->first();
    }
}
