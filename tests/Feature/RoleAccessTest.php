<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Acceptance criterion: no user reaches a function outside their role.
 * These tests walk through EVERY registered route protected by a role, so
 * routes added in later phases are covered automatically.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<RoutingRoute> */
    private function routesFor(string $role): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array("role:$role", $route->gatherMiddleware(), true))
            ->values()
            ->all();
    }

    private function callEveryMethod(User $user, RoutingRoute $route, int $expectedStatus): void
    {
        // Fill route parameters with an id: the role check runs before model binding.
        $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $response = $this->actingAs($user)->call($method, '/'.ltrim($uri, '/'));

            $this->assertSame(
                $expectedStatus,
                $response->getStatusCode(),
                "$method /$uri should return $expectedStatus for a {$user->role->value}.",
            );
        }
    }

    public function test_every_teacher_route_is_forbidden_to_students(): void
    {
        $routes = $this->routesFor('teacher');
        $this->assertNotEmpty($routes);

        $student = User::factory()->student()->create();
        foreach ($routes as $route) {
            $this->callEveryMethod($student, $route, 403);
        }
    }

    public function test_every_student_route_is_forbidden_to_teachers(): void
    {
        $routes = $this->routesFor('student');
        $this->assertNotEmpty($routes);

        $teacher = User::factory()->teacher()->create();
        foreach ($routes as $route) {
            $this->callEveryMethod($teacher, $route, 403);
        }
    }

    public function test_every_role_route_also_requires_authentication(): void
    {
        foreach ([...$this->routesFor('teacher'), ...$this->routesFor('student')] as $route) {
            $this->assertContains('auth', $route->gatherMiddleware(), "/{$route->uri()} must require login.");
        }
    }

    public function test_forbidden_page_explains_the_reason_in_french(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('teacher.dashboard'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403)
                ->where('message', 'Cette page est réservée aux enseignants.'));
    }

    public function test_unknown_page_shows_the_french_404_page(): void
    {
        $this->get('/page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
    }
}
