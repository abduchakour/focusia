<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $teacher = $request->user();

        return Inertia::render('teacher/Dashboard', [
            'stats' => [
                'groups' => $teacher->teachingGroups()->count(),
                // Distinct students: one student in two groups counts once.
                'students' => Enrollment::active()
                    ->whereIn('group_id', $teacher->teachingGroups()->select('id'))
                    ->distinct()
                    ->count('student_id'),
                'quizzes' => $teacher->quizzes()->count(),
            ],
        ]);
    }
}
