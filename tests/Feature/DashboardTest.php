<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/enseignant')->assertRedirect('/login');
        $this->get('/etudiant')->assertRedirect('/login');
    }

    public function test_teacher_is_sent_to_the_teacher_dashboard(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get('/dashboard')->assertRedirect(route('teacher.dashboard'));
        $this->actingAs($teacher)->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('teacher/Dashboard')->has('stats'));
    }

    public function test_student_is_sent_to_the_student_dashboard(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get('/dashboard')->assertRedirect(route('student.dashboard'));
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('student/Dashboard')->where('stats.average', null));
    }

    public function test_shared_user_data_never_exposes_secrets(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', 'student')
                ->where('auth.user.role_label', 'Étudiant')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }
}
