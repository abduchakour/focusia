<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user();
        $graded = $student->attempts()->where('status', AttemptStatus::Submitted);
        $average = (clone $graded)->avg('score');

        // Open quizzes not finished yet, the closest due date first.
        $todo = Quiz::visibleTo($student)
            ->where('due_at', '>', now())
            ->whereDoesntHave('attempts', fn ($q) => $q->where('student_id', $student->id)->where('status', AttemptStatus::Submitted))
            ->withCount('questions')
            ->orderBy('due_at')
            ->limit(3)
            ->get()
            ->map(fn (Quiz $quiz) => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'subject' => $quiz->subject,
                'questions_count' => $quiz->questions_count,
                'duration_minutes' => $quiz->duration_minutes,
                'due_at' => $quiz->due_at->toIso8601String(),
            ]);

        $recent = (clone $graded)
            ->whereHas('quiz', fn ($q) => $q->visibleTo($student))
            ->with('quiz:id,title,subject')
            ->latest('answered_at')
            ->limit(3)
            ->get()
            ->map(fn (Attempt $attempt) => [
                'quiz_id' => $attempt->quiz_id,
                'title' => $attempt->quiz->title,
                'subject' => $attempt->quiz->subject,
                'score' => (float) $attempt->score,
                'answered_at' => $attempt->answered_at->toIso8601String(),
            ]);

        return Inertia::render('student/Dashboard', [
            'stats' => [
                'groups' => $student->groups()->count(),
                'attempts' => (clone $graded)->count(),
                'average' => $average === null ? null : round((float) $average, 2),
            ],
            'todo' => $todo,
            'recent' => $recent,
        ]);
    }
}
