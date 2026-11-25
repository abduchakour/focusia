<?php

use App\Http\Controllers\FileController;
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

    // Files (both roles, rights checked by the policies): a temporary signed link, then the download.
    Route::get('fichiers/{kind}/{id}', [FileController::class, 'link'])->whereNumber('id')->name('files.link');
    Route::get('telechargement/{kind}/{id}', [FileController::class, 'download'])->whereNumber('id')->middleware('signed')->name('files.download');

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

        // Results and understanding (phase 5)
        Route::get('quiz/{quiz}/resultats', Teacher\QuizResultController::class)->name('quizzes.results');
        Route::get('groupes/{group}/suivi', Teacher\GroupInsightController::class)->name('groups.insights');

        // Course materials and library (phase 6). Updates with a file are sent as POST + _method=PUT.
        Route::get('supports', [Teacher\MaterialController::class, 'index'])->name('materials.index');
        Route::get('supports/nouveau', [Teacher\MaterialController::class, 'create'])->name('materials.create');
        Route::post('supports', [Teacher\MaterialController::class, 'store'])->name('materials.store');
        Route::get('supports/{material}/modifier', [Teacher\MaterialController::class, 'edit'])->name('materials.edit');
        Route::put('supports/{material}', [Teacher\MaterialController::class, 'update'])->name('materials.update');
        Route::delete('supports/{material}', [Teacher\MaterialController::class, 'destroy'])->name('materials.destroy');

        // Homework (phase 7)
        Route::get('devoirs', [Teacher\AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('devoirs/nouveau', [Teacher\AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('devoirs', [Teacher\AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('devoirs/{assignment}', [Teacher\AssignmentController::class, 'show'])->name('assignments.show');
        Route::get('devoirs/{assignment}/modifier', [Teacher\AssignmentController::class, 'edit'])->name('assignments.edit');
        Route::put('devoirs/{assignment}', [Teacher\AssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('devoirs/{assignment}', [Teacher\AssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::put('devoirs/{assignment}/rendus/{submission}', [Teacher\AssignmentController::class, 'grade'])->name('assignments.grade');

        Route::get('bibliotheque', [Teacher\ResourceController::class, 'index'])->name('resources.index');
        Route::get('bibliotheque/nouveau', [Teacher\ResourceController::class, 'create'])->name('resources.create');
        Route::post('bibliotheque', [Teacher\ResourceController::class, 'store'])->name('resources.store');
        Route::get('bibliotheque/{resource}/modifier', [Teacher\ResourceController::class, 'edit'])->name('resources.edit');
        Route::put('bibliotheque/{resource}', [Teacher\ResourceController::class, 'update'])->name('resources.update');
        Route::delete('bibliotheque/{resource}', [Teacher\ResourceController::class, 'destroy'])->name('resources.destroy');
    });

    // Student space. Every route here is forbidden to teachers.
    Route::prefix('etudiant')->name('student.')->middleware('role:student')->group(function () {
        Route::get('/', Student\DashboardController::class)->name('dashboard');

        // Groups (phase 2)
        Route::get('groupes', [Student\GroupController::class, 'index'])->name('groups.index');
        Route::post('groupes/rejoindre', [Student\GroupController::class, 'join'])->middleware('throttle:join-group')->name('groups.join');

        // Taking quizzes, online or offline (phase 4)
        Route::get('quiz', [Student\QuizController::class, 'index'])->name('quizzes.index');
        Route::post('quiz/{quiz}/commencer', [Student\QuizController::class, 'start'])->name('quizzes.start');
        Route::get('quiz/{quiz}/passer', [Student\QuizController::class, 'take'])->name('quizzes.take');
        Route::post('quiz/{quiz}/reponses', [Student\QuizController::class, 'submit'])->middleware('throttle:30,1')->name('quizzes.submit');
        Route::get('quiz/{quiz}/resultat', [Student\QuizController::class, 'result'])->name('quizzes.result');

        // Course materials and library (phase 6)
        Route::get('supports', [Student\ContentController::class, 'materials'])->name('materials.index');
        Route::get('bibliotheque', [Student\ContentController::class, 'resources'])->name('resources.index');

        // Homework (phase 7)
        Route::get('devoirs', [Student\AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('devoirs/{assignment}', [Student\AssignmentController::class, 'show'])->name('assignments.show');
        Route::post('devoirs/{assignment}/rendu', [Student\AssignmentController::class, 'submit'])->name('assignments.submit');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
