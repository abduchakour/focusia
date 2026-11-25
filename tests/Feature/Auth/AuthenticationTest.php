<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_wrong_credentials_show_a_french_message(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'mauvais'])
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent pas à nos enregistrements.']);
    }

    public function test_login_is_blocked_after_too_many_failed_attempts(): void
    {
        config(['focusia.login_max_attempts' => 3]);
        $user = User::factory()->create();

        foreach (range(1, 3) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'mauvais']);
        }

        // Even the right password is refused while the account is locked.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Tentatives de connexion trop nombreuses', session('errors')->first('email'));
    }

    public function test_one_ip_cannot_flood_the_login_form(): void
    {
        foreach (range(1, 20) as $i) {
            $this->post('/login', ['email' => "inconnu$i@example.com", 'password' => 'x']);
        }

        $this->post('/login', ['email' => 'encore@example.com', 'password' => 'x'])->assertStatus(429);
    }

    public function test_session_is_regenerated_on_login(): void
    {
        $user = User::factory()->create();
        $this->startSession();
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
