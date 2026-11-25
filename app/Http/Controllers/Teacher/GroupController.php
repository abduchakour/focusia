<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Level;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\GroupRequest;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = $request->user()->teachingGroups()
            ->withCount('students')
            ->latest()
            ->get()
            ->map(fn (Group $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject,
                'level' => $group->level->label(),
                'access_code' => $group->access_code,
                'students_count' => $group->students_count,
            ]);

        return Inertia::render('teacher/groups/Index', [
            'groups' => $groups,
            'levels' => self::levelOptions(),
        ]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        $group = $request->user()->teachingGroups()->create($request->validated());

        return to_route('teacher.groups.show', $group)
            ->with('success', "Groupe « {$group->name} » créé. Partagez le code {$group->access_code} avec vos étudiants.");
    }

    public function show(Group $group): Response
    {
        Gate::authorize('manage', $group);

        $enrollments = $group->enrollments()
            ->with('student:id,first_name,last_name,email')
            ->orderBy('joined_at')
            ->get();

        $toArray = fn (Enrollment $e) => [
            'id' => $e->student->id,
            'name' => $e->student->name,
            'email' => $e->student->email,
            'joined_at' => $e->joined_at->toIso8601String(),
            'removed_at' => $e->removed_at?->toIso8601String(),
        ];

        return Inertia::render('teacher/groups/Show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject,
                'level' => $group->level->value,
                'level_label' => $group->level->label(),
                'access_code' => $group->access_code,
                'invite_url' => route('student.groups.index', ['code' => $group->access_code]),
            ],
            'students' => $enrollments->filter->isActive()->map($toArray)->values(),
            'removedStudents' => $enrollments->reject->isActive()->map($toArray)->values(),
            'levels' => self::levelOptions(),
        ]);
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        Gate::authorize('manage', $group);

        $group->update($request->validated());

        return back()->with('success', 'Groupe mis à jour.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        Gate::authorize('manage', $group);

        $name = $group->name;
        $group->delete();

        return to_route('teacher.groups.index')->with('success', "Groupe « {$name} » supprimé.");
    }

    /** The old code stops working immediately; current members are not affected. */
    public function regenerateCode(Group $group): RedirectResponse
    {
        Gate::authorize('manage', $group);

        $group->update(['access_code' => Group::generateAccessCode()]);

        return back()->with('success', "Nouveau code d'accès : {$group->access_code}. L'ancien code ne fonctionne plus.");
    }

    /** Rule 18: the student loses access, but their results and submissions are kept. */
    public function removeStudent(Group $group, User $student): RedirectResponse
    {
        Gate::authorize('manage', $group);

        $enrollment = $group->enrollments()->where('student_id', $student->id)->whereNull('removed_at')->firstOrFail();
        $enrollment->update(['removed_at' => now()]);

        return back()->with('success', "{$student->name} a été retiré(e) du groupe. Ses résultats sont conservés.");
    }

    public function restoreStudent(Group $group, User $student): RedirectResponse
    {
        Gate::authorize('manage', $group);

        $enrollment = $group->enrollments()->where('student_id', $student->id)->whereNotNull('removed_at')->firstOrFail();
        $enrollment->update(['removed_at' => null]);

        return back()->with('success', "{$student->name} a été réintégré(e) dans le groupe.");
    }

    /** @return list<array{value: string, label: string}> */
    public static function levelOptions(): array
    {
        return array_map(fn (Level $level) => ['value' => $level->value, 'label' => $level->label()], Level::cases());
    }
}
