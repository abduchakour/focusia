<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = $request->user()->groups()
            ->with('teacher:id,first_name,last_name')
            ->orderBy('enrollments.joined_at', 'desc')
            ->get()
            ->map(fn (Group $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject,
                'level' => $group->level->label(),
                'teacher' => $group->teacher->name,
            ]);

        return Inertia::render('student/groups/Index', [
            'groups' => $groups,
            // Pre-filled when the student opens an invitation link (?code=XXXXXX).
            'code' => self::normalize((string) $request->query('code', '')),
        ]);
    }

    public function join(Request $request): RedirectResponse
    {
        $request->merge(['code' => self::normalize((string) $request->input('code', ''))]);
        $request->validate(
            ['code' => ['required', 'string', 'size:6']],
            ['code.required' => "Saisissez le code d'accès donné par votre enseignant.", 'code.size' => 'Le code contient 6 caractères.'],
        );

        $group = Group::firstWhere('access_code', $request->input('code'));
        if (! $group) {
            throw ValidationException::withMessages(['code' => 'Aucun groupe ne correspond à ce code. Vérifiez-le auprès de votre enseignant.']);
        }

        $student = $request->user();
        $enrollment = $group->enrollments()->where('student_id', $student->id)->first();

        if ($enrollment?->isActive()) {
            throw ValidationException::withMessages(['code' => "Vous êtes déjà membre du groupe « {$group->name} »."]);
        }
        if ($enrollment) {
            // Decision 2026-11-25: a removed student cannot come back with the code.
            throw ValidationException::withMessages(['code' => 'Vous avez été retiré(e) de ce groupe. Seul votre enseignant peut vous réintégrer.']);
        }

        $group->enrollments()->create(['student_id' => $student->id, 'joined_at' => now()]);

        return to_route('student.groups.index')->with('success', "Bienvenue dans le groupe « {$group->name} » !");
    }

    /** Codes are typed on phones: ignore case, spaces and dashes. */
    private static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', $code) ?? '');
    }
}
