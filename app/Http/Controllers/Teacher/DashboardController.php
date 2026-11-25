<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Quiz;
use App\Services\Insights;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Insights $insights): Response
    {
        $teacher = $request->user();
        $groups = $teacher->teachingGroups()->orderBy('name')->get();

        // One summary per group (active students only); a student in two groups may appear twice.
        $flagged = collect();
        $summaries = $groups->map(function (Group $group) use ($insights, $flagged) {
            $data = $insights->group($group);
            $data['students']->where('difficulty', true)->each(fn ($s) => $flagged->push($s['id']));
            $averages = $data['students']->pluck('average')->filter(fn ($a) => $a !== null);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'students' => $data['students']->count(),
                'average' => $averages->isEmpty() ? null : round($averages->avg(), 2),
                'difficulty' => $data['students']->where('difficulty', true)->take(5)->map(fn ($s) => [
                    'id' => $s['id'], 'name' => $s['name'], 'reasons' => $s['reasons'],
                ])->values(),
                'difficulty_count' => $data['students']->where('difficulty', true)->count(),
            ];
        });

        $activeStudentIds = Enrollment::active()->whereIn('group_id', $groups->pluck('id'))->pluck('student_id')->unique()->all();
        $quizIds = $teacher->quizzes()->published()->pluck('id')->all();

        $recent = $teacher->quizzes()->published()
            ->withCount(['attempts as submitted_count' => fn ($q) => $q->where('status', AttemptStatus::Submitted)])
            ->withAvg(['attempts as average' => fn ($q) => $q->where('status', AttemptStatus::Submitted)], 'score')
            ->orderByDesc('due_at')
            ->limit(4)
            ->get()
            ->map(fn (Quiz $quiz) => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'due_at' => $quiz->due_at->toIso8601String(),
                'is_open' => $quiz->due_at->isFuture(),
                'submitted' => $quiz->submitted_count,
                'expected' => Enrollment::active()->whereIn('group_id', $quiz->groups()->select('groups.id'))->distinct()->count('student_id'),
                'average' => $quiz->average === null ? null : round((float) $quiz->average, 2),
            ]);

        return Inertia::render('teacher/Dashboard', [
            'stats' => [
                'groups' => $groups->count(),
                // Distinct students: one student in two groups counts once.
                'students' => count($activeStudentIds),
                'quizzes' => $teacher->quizzes()->count(),
                'difficulty' => $flagged->unique()->count(),
            ],
            'groups' => $summaries,
            // The notions understood the least, all groups together.
            'weakNotions' => $insights->notionRates($quizIds, $activeStudentIds)
                ->filter(fn ($n) => $n->rate !== null)
                ->sortBy('rate')
                ->take(4)
                ->map(fn ($n) => ['name' => $n->name, 'rate' => $n->rate, 'answered' => $n->answered])
                ->values(),
            'recentQuizzes' => $recent,
        ]);
    }
}
