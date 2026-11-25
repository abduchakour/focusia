<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // Teacher accounts need the code given by the institution (config focusia.teacher_code).
            'teacher_code' => [
                Rule::requiredIf($request->input('role') === Role::Teacher->value),
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ($request->input('role') !== Role::Teacher->value) {
                        return;
                    }
                    $expected = (string) config('focusia.teacher_code');
                    if ($expected === '') {
                        $fail("L'inscription des enseignants est fermée pour le moment. Contactez l'administration de votre établissement.");
                    } elseif (! hash_equals(mb_strtoupper(trim($expected)), mb_strtoupper(trim((string) $value)))) {
                        $fail("Code enseignant incorrect. Demandez-le à l'administration de votre établissement.");
                    }
                },
            ],
        ], [
            'teacher_code.required' => 'Saisissez le code enseignant donné par votre établissement.',
            'role.required' => 'Indiquez si vous êtes enseignant ou étudiant.',
            'role.enum' => 'Indiquez si vous êtes enseignant ou étudiant.',
        ]);

        $user = User::create([
            'role' => $validated['role'],
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
