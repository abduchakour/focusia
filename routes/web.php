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
    });

    // Student space. Every route here is forbidden to teachers.
    Route::prefix('etudiant')->name('student.')->middleware('role:student')->group(function () {
        Route::get('/', Student\DashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
