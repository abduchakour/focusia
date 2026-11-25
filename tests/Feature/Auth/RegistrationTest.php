<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return [
            'role' => 'student',
            'first_name' => 'Hodan',
            'last_name' => 'Robleh',
            'email' => 'hodan@example.com',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
            ...$overrides,
        ];
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_a_student_can_register(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::firstWhere('email', 'hodan@example.com');
        $this->assertSame(Role::Student, $user->role);
        $this->assertSame(Institution::DEFAULT_ID, $user->institution_id);
    }

    public function test_a_teacher_registers_with_the_institution_code(): void
    {
        // Case and spaces do not matter (typed on a phone).
        $this->post('/register', $this->payload(['role' => 'teacher', 'email' => 'prof@example.com', 'teacher_code' => ' prof-test ']))
            ->assertSessionHasNoErrors();

        $this->assertSame(Role::Teacher, User::firstWhere('email', 'prof@example.com')->role);
    }

    public function test_nobody_becomes_a_teacher_without_the_right_code(): void
    {
        $this->post('/register', $this->payload(['role' => 'teacher']))
            ->assertSessionHasErrors(['teacher_code' => 'Saisissez le code enseignant donné par votre établissement.']);
        $this->post('/register', $this->payload(['role' => 'teacher', 'teacher_code' => 'DEVINETTE']))
            ->assertSessionHasErrors(['teacher_code' => "Code enseignant incorrect. Demandez-le à l'administration de votre établissement."]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_teacher_registration_is_closed_without_a_configured_code(): void
    {
        config(['focusia.teacher_code' => '']);

        $this->post('/register', $this->payload(['role' => 'teacher', 'teacher_code' => '']))
            ->assertSessionHasErrors('teacher_code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_student_never_needs_a_code(): void
    {
        $this->post('/register', $this->payload(['teacher_code' => 'n importe quoi']))->assertSessionHasNoErrors();

        $this->assertSame(Role::Student, User::firstWhere('email', 'hodan@example.com')->role);
    }

    public function test_role_is_required_and_limited_to_teacher_or_student(): void
    {
        $this->post('/register', $this->payload(['role' => '']))
            ->assertSessionHasErrors(['role' => 'Indiquez si vous êtes enseignant ou étudiant.']);

        $this->post('/register', $this->payload(['role' => 'admin']))
            ->assertSessionHasErrors('role');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_is_hashed_with_argon2id(): void
    {
        $this->post('/register', $this->payload());

        $hash = User::firstWhere('email', 'hodan@example.com')->password;
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(Hash::check('motdepasse1', $hash));
    }

    public function test_password_needs_letters_and_numbers(): void
    {
        $this->post('/register', $this->payload(['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh']))
            ->assertSessionHasErrors('password');
    }

    public function test_validation_messages_are_in_french(): void
    {
        $this->post('/register', $this->payload(['email' => 'pas-un-email']))
            ->assertSessionHasErrors(['email' => 'Le champ adresse e-mail doit être une adresse e-mail valide.']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'hodan@example.com']);

        $this->post('/register', $this->payload())->assertSessionHasErrors('email');
    }
}
