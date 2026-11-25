<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();
    }

    private function groupOf(User $teacher): Group
    {
        return Group::factory()->create(['teacher_id' => $teacher->id]);
    }

    // ---------- Teacher: create, update, delete ----------

    public function test_acceptance_teacher_creates_a_group_and_a_student_joins_it_with_the_code(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.groups.store'), ['name' => 'L1 Informatique – TD2', 'subject' => 'Algorithmique', 'level' => 'L1'])
            ->assertSessionHasNoErrors();

        $group = Group::firstWhere('name', 'L1 Informatique – TD2');
        $this->assertSame($this->teacher->id, $group->teacher_id);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $group->access_code);

        $this->actingAs($this->student)
            ->post(route('student.groups.join'), ['code' => $group->access_code])
            ->assertRedirect(route('student.groups.index'))
            ->assertSessionHas('success');

        $this->assertTrue($group->students()->whereKey($this->student->id)->exists());
    }

    public function test_group_creation_redirects_to_the_group_with_its_code(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.groups.store'), ['name' => 'TD1', 'subject' => 'Réseaux', 'level' => 'L2']);

        $group = Group::firstWhere('name', 'TD1');
        $response->assertRedirect(route('teacher.groups.show', $group));
        $this->assertStringContainsString($group->access_code, session('success'));
    }

    public function test_group_fields_are_validated_in_french(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.groups.store'), ['name' => '', 'subject' => '', 'level' => 'L9'])
            ->assertSessionHasErrors(['name', 'subject', 'level']);

        $this->assertStringContainsString('nom du groupe', session('errors')->first('name'));
        $this->assertDatabaseCount('groups', 0);
    }

    public function test_teacher_can_update_and_delete_own_group(): void
    {
        $group = $this->groupOf($this->teacher);
        $code = $group->access_code;

        $this->actingAs($this->teacher)
            ->put(route('teacher.groups.update', $group), ['name' => 'Nouveau nom', 'subject' => 'Maths', 'level' => 'M1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Nouveau nom', $group->fresh()->name);
        $this->assertSame($code, $group->fresh()->access_code); // editing keeps the code

        $this->actingAs($this->teacher)->delete(route('teacher.groups.destroy', $group))->assertRedirect(route('teacher.groups.index'));
        $this->assertModelMissing($group);
    }

    public function test_teacher_lists_only_own_groups(): void
    {
        $mine = $this->groupOf($this->teacher);
        $this->groupOf(User::factory()->teacher()->create());

        $this->actingAs($this->teacher)->get(route('teacher.groups.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('teacher/groups/Index')
                ->has('groups', 1)
                ->where('groups.0.id', $mine->id));
    }

    // ---------- Rule 9: a teacher only manages their own groups ----------

    public function test_teacher_cannot_touch_another_teachers_group(): void
    {
        $other = $this->groupOf(User::factory()->teacher()->create());
        $other->enrollments()->create(['student_id' => $this->student->id]);
        $this->actingAs($this->teacher);

        $this->get(route('teacher.groups.show', $other))->assertForbidden();
        $this->put(route('teacher.groups.update', $other), ['name' => 'X', 'subject' => 'Y', 'level' => 'L1'])->assertForbidden();
        $this->put(route('teacher.groups.update', $other), ['name' => ''])->assertForbidden(); // 403 even with invalid data
        $this->post(route('teacher.groups.code', $other))->assertForbidden();
        $this->delete(route('teacher.groups.students.remove', [$other, $this->student]))->assertForbidden();
        $this->delete(route('teacher.groups.destroy', $other))->assertForbidden();

        $this->assertModelExists($other);
        $this->assertTrue($other->students()->whereKey($this->student->id)->exists());
    }

    // ---------- Access code ----------

    public function test_regenerated_code_replaces_the_old_one_and_keeps_members(): void
    {
        $group = $this->groupOf($this->teacher);
        $group->enrollments()->create(['student_id' => $this->student->id]);
        $oldCode = $group->access_code;

        $this->actingAs($this->teacher)->post(route('teacher.groups.code', $group))->assertSessionHas('success');

        $newCode = $group->fresh()->access_code;
        $this->assertNotSame($oldCode, $newCode);
        $this->assertSame(1, $group->students()->count());

        $newcomer = User::factory()->student()->create();
        $this->actingAs($newcomer)->post(route('student.groups.join'), ['code' => $oldCode])->assertSessionHasErrors('code');
        $this->actingAs($newcomer)->post(route('student.groups.join'), ['code' => $newCode])->assertSessionHasNoErrors();
    }

    public function test_code_is_accepted_in_lowercase_with_spaces(): void
    {
        $group = $this->groupOf($this->teacher);
        $typed = strtolower(substr($group->access_code, 0, 3).' '.substr($group->access_code, 3));

        $this->actingAs($this->student)->post(route('student.groups.join'), ['code' => $typed])->assertSessionHasNoErrors();
        $this->assertTrue($group->students()->whereKey($this->student->id)->exists());
    }

    public function test_unknown_code_is_refused_with_a_clear_message(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.groups.join'), ['code' => 'ZZZZZZ'])
            ->assertSessionHasErrors(['code' => 'Aucun groupe ne correspond à ce code. Vérifiez-le auprès de votre enseignant.']);
    }

    public function test_joining_twice_is_refused(): void
    {
        $group = $this->groupOf($this->teacher);
        $this->actingAs($this->student)->post(route('student.groups.join'), ['code' => $group->access_code]);

        $this->actingAs($this->student)->post(route('student.groups.join'), ['code' => $group->access_code])->assertSessionHasErrors('code');
        $this->assertSame(1, $group->enrollments()->count());
    }

    public function test_code_guessing_is_rate_limited(): void
    {
        $this->actingAs($this->student);
        foreach (range(1, 10) as $_) {
            $this->post(route('student.groups.join'), ['code' => 'AAAAAA']);
        }

        $this->post(route('student.groups.join'), ['code' => 'AAAAAA'])->assertStatus(429);
    }

    // ---------- Rule 1: several groups ----------

    public function test_rule_1_student_in_several_groups_teacher_with_several_groups(): void
    {
        $g1 = $this->groupOf($this->teacher);
        $g2 = $this->groupOf($this->teacher);

        $this->actingAs($this->student)->post(route('student.groups.join'), ['code' => $g1->access_code]);
        $this->actingAs($this->student)->post(route('student.groups.join'), ['code' => $g2->access_code]);

        $this->actingAs($this->student)->get(route('student.groups.index'))
            ->assertInertia(fn (Assert $page) => $page->component('student/groups/Index')->has('groups', 2));
        $this->assertSame(2, $this->teacher->teachingGroups()->count());
    }

    public function test_invitation_link_prefills_the_code(): void
    {
        $this->actingAs($this->student)->get(route('student.groups.index', ['code' => 'abc-123']))
            ->assertInertia(fn (Assert $page) => $page->where('code', 'ABC123'));
    }

    // ---------- Rule 18: removal ----------

    public function test_rule_18_removed_student_loses_the_group_but_keeps_results(): void
    {
        $group = $this->groupOf($this->teacher);
        $group->enrollments()->create(['student_id' => $this->student->id]);
        $quiz = Quiz::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Q', 'subject' => 'S', 'level' => 'L1',
            'due_at' => now()->subDay(), 'corrections_at' => now(), 'published_at' => now()->subWeek(),
        ]);
        Attempt::create(['quiz_id' => $quiz->id, 'student_id' => $this->student->id, 'status' => AttemptStatus::Submitted, 'started_at' => now()->subDays(2), 'score' => 14]);

        $this->actingAs($this->teacher)
            ->delete(route('teacher.groups.students.remove', [$group, $this->student]))
            ->assertSessionHas('success');

        $this->actingAs($this->student)->get(route('student.groups.index'))
            ->assertInertia(fn (Assert $page) => $page->has('groups', 0));
        $this->assertSame(1, $this->student->attempts()->count());

        $this->actingAs($this->teacher)->get(route('teacher.groups.show', $group))
            ->assertInertia(fn (Assert $page) => $page->has('students', 0)->has('removedStudents', 1));
    }

    public function test_removed_student_cannot_rejoin_with_the_code_but_teacher_can_reinstate(): void
    {
        $group = $this->groupOf($this->teacher);
        $group->enrollments()->create(['student_id' => $this->student->id, 'removed_at' => now()]);

        $this->actingAs($this->student)
            ->post(route('student.groups.join'), ['code' => $group->access_code])
            ->assertSessionHasErrors(['code' => 'Vous avez été retiré(e) de ce groupe. Seul votre enseignant peut vous réintégrer.']);

        $this->actingAs($this->teacher)->post(route('teacher.groups.students.restore', [$group, $this->student]))->assertSessionHas('success');
        $this->assertTrue($group->students()->whereKey($this->student->id)->exists());
    }

    public function test_removing_a_student_who_is_not_in_the_group_returns_404(): void
    {
        $group = $this->groupOf($this->teacher);

        $this->actingAs($this->teacher)->delete(route('teacher.groups.students.remove', [$group, $this->student]))->assertNotFound();
    }

    public function test_teacher_dashboard_counts_each_student_once(): void
    {
        $g1 = $this->groupOf($this->teacher);
        $g2 = $this->groupOf($this->teacher);
        $g1->enrollments()->create(['student_id' => $this->student->id]);
        $g2->enrollments()->create(['student_id' => $this->student->id]);

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.groups', 2)->where('stats.students', 1));
    }
}
