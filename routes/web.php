<?php

use App\Http\Controllers\Student;
use App\Http\Controllers\Teacher;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware('auth')->group(function () {
    // Single entry point after login: sends each role to its own space.
    Route::get('dashboard', function () {
        return redirect()->route(request()->user()->isTeacher() ? 'teacher.dashboard' : 'student.dashboard');
    })->name('dashboard');

    // Teacher space. Every route here is forbidden to students (tested in RoleAccessTest).
    Route::prefix('enseignant')->name('teacher.')->middleware('role:teacher')->group(function () {
        Route::get('/', Teacher\DashboardController::class)->name('dashboard');

        // Groups (phase 2)
        Route::get('groupes', [Teacher\GroupController::class, 'index'])->name('groups.index');
        Route::post('groupes', [Teacher\GroupController::class, 'store'])->name('groups.store');
        Route::get('groupes/{group}', [Teacher\GroupController::class, 'show'])->name('groups.show');
        Route::put('groupes/{group}', [Teacher\GroupController::class, 'update'])->name('groups.update');
        Route::delete('groupes/{group}', [Teacher\GroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('groupes/{group}/code', [Teacher\GroupController::class, 'regenerateCode'])->name('groups.code');
        Route::delete('groupes/{group}/etudiants/{student}', [Teacher\GroupController::class, 'removeStudent'])->name('groups.students.remove');
        Route::post('groupes/{group}/etudiants/{student}/reintegrer', [Teacher\GroupController::class, 'restoreStudent'])->name('groups.students.restore');

        // Notions and quizzes (phase 3)
        Route::get('notions', [Teacher\NotionController::class, 'index'])->name('notions.index');
        Route::post('notions', [Teacher\NotionController::class, 'store'])->name('notions.store');
        Route::put('notions/{notion}', [Teacher\NotionController::class, 'update'])->name('notions.update');
        Route::delete('notions/{notion}', [Teacher\NotionController::class, 'destroy'])->name('notions.destroy');

        Route::get('quiz', [Teacher\QuizController::class, 'index'])->name('quizzes.index');
        Route::get('quiz/nouveau', [Teacher\QuizController::class, 'create'])->name('quizzes.create');
        Route::post('quiz', [Teacher\QuizController::class, 'store'])->name('quizzes.store');
        Route::get('quiz/{quiz}/modifier', [Teacher\QuizController::class, 'edit'])->name('quizzes.edit');
        Route::put('quiz/{quiz}', [Teacher\QuizController::class, 'update'])->name('quizzes.update');
        Route::delete('quiz/{quiz}', [Teacher\QuizController::class, 'destroy'])->name('quizzes.destroy');
        Route::post('quiz/{quiz}/dupliquer', [Teacher\QuizController::class, 'duplicate'])->name('quizzes.duplicate');
    });

    // Student space. Every route here is forbidden to teachers.
    Route::prefix('etudiant')->name('student.')->middleware('role:student')->group(function () {
        Route::get('/', Student\DashboardController::class)->name('dashboard');

        // Groups (phase 2)
        Route::get('groupes', [Student\GroupController::class, 'index'])->name('groups.index');
        Route::post('groupes/rejoindre', [Student\GroupController::class, 'join'])->middleware('throttle:join-group')->name('groups.join');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
