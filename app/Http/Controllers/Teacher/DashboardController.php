<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
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
                'students' => $teacher->teachingGroups()->withCount('students')->get()->sum('students_count'),
                'quizzes' => $teacher->quizzes()->count(),
            ],
        ]);
    }
}
