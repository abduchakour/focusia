<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
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

        return Inertia::render('student/Dashboard', [
            'stats' => [
                'groups' => $student->groups()->count(),
                'attempts' => (clone $graded)->count(),
                'average' => $average === null ? null : round((float) $average, 2),
            ],
        ]);
    }
}
