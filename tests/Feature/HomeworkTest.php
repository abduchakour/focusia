<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Group;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Phase 7: homework (rules 9, 11, 13, 14, 15, 16, 17, 18). */
class HomeworkTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('focusia.files_disk'));
        Carbon::setTestNow('2026-12-01 10:00:00');

        $this->teacher = User::factory()->teacher()->create();
        $this->group = Group::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->student = User::factory()->student()->create();
        $this->group->enrollments()->create(['student_id' => $this->student->id, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assignment(array $attributes = []): Assignment
    {
        $assignment = Assignment::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Devoir 1', 'instructions' => 'Écrire le tri.',
            'due_at' => now()->addDays(2), 'allow_late' => false, ...$attributes,
        ]);
        $assignment->groups()->attach($this->group->id);

        return $assignment;
    }

    private function submit(Assignment $assignment, array $data)
    {
        return $this->actingAs($this->student)->post(route('student.assignments.submit', ['assignment' => $assignment->id]), $data);
    }

    // ---------- Teacher (rules 11, 13) ----------

    public function test_rule_13_a_homework_needs_a_due_date_and_the_material_is_optional(): void
    {
        $material = Material::create(['teacher_id' => $this->teacher->id, 'title' => 'Cours', 'type' => 'course', 'url' => 'https://exemple.dj']);
        $payload = ['title' => 'Devoir', 'instructions' => 'Faire.', 'due_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'group_ids' => [$this->group->id]];

        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [...$payload, 'due_at' => ''])->assertSessionHasErrors('due_at');
        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [...$payload, 'material_id' => $material->id])->assertSessionHasNoErrors();
        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [...$payload, 'group_ids' => [$this->group->id, $this->group->id]])
            ->assertSessionHasErrors('group_ids.1');

        $this->assertSame([null, $material->id], Assignment::orderBy('id')->pluck('material_id')->all());
    }

    public function test_rule_9_another_teacher_cannot_see_edit_grade_or_delete(): void
    {
        $assignment = $this->assignment();
        $submission = $assignment->submissions()->create(['student_id' => $this->student->id, 'body' => 'Réponse', 'submitted_at' => now()]);
        $intruder = User::factory()->teacher()->create();

        $this->actingAs($intruder)->get(route('teacher.assignments.show', ['assignment' => $assignment->id]))->assertForbidden();
        $this->actingAs($intruder)->put(route('teacher.assignments.update', ['assignment' => $assignment->id]), [])->assertForbidden();
        $this->actingAs($intruder)->put(route('teacher.assignments.grade', ['assignment' => $assignment->id, 'submission' => $submission->id]), ['grade' => 20])->assertForbidden();
        $this->actingAs($intruder)->delete(route('teacher.assignments.destroy', ['assignment' => $assignment->id]))->assertForbidden();
        $this->assertNull($submission->fresh()->grade);
    }

    // ---------- Student submissions (rules 14, 15) ----------

    public function test_rule_14_a_file_a_text_or_both_and_one_submission_replaced_until_the_due_date(): void
    {
        $assignment = $this->assignment();

        $this->submit($assignment, [])->assertSessionHasErrors(['body' => 'Joignez un fichier, écrivez votre réponse, ou les deux.']);
        $this->submit($assignment, ['body' => 'Version 1'])->assertSessionHas('success', 'Devoir rendu !');
        $this->submit($assignment, ['file' => UploadedFile::fake()->create('tri.pdf', 100, 'application/pdf')])->assertSessionHas('success', 'Votre rendu a été remplacé.');

        $this->assertSame(1, Submission::count());
        $submission = Submission::first();
        $this->assertNull($submission->body);
        $this->assertSame('tri.pdf', $submission->file_name);
        $this->assertFalse($submission->is_late);
    }

    public function test_rule_15_after_the_due_date_refused_unless_late_work_is_allowed_then_flagged_late(): void
    {
        $strict = $this->assignment(['due_at' => now()->subHour()]);
        $lenient = $this->assignment(['title' => 'Devoir 2', 'due_at' => now()->subHour(), 'allow_late' => true]);

        $this->submit($strict, ['body' => 'Trop tard'])->assertSessionHas('error');
        $this->submit($lenient, ['body' => 'En retard'])->assertSessionHas('success', 'Devoir rendu ! Il est signalé en retard.');

        $this->assertSame(0, $strict->submissions()->count());
        $this->assertTrue($lenient->submissions()->first()->is_late);
    }

    public function test_rule_20_a_submitted_file_follows_the_same_type_rules(): void
    {
        $this->submit($this->assignment(), ['file' => UploadedFile::fake()->create('virus.pdf', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->assertSame(0, Submission::count());
    }

    public function test_a_graded_submission_can_no_longer_be_replaced(): void
    {
        $assignment = $this->assignment();
        $assignment->submissions()->create(['student_id' => $this->student->id, 'body' => 'Rendu', 'submitted_at' => now(), 'grade' => 15]);

        $this->submit($assignment, ['body' => 'Nouvelle version'])->assertSessionHas('error');
        $this->assertSame('Rendu', Submission::first()->body);
    }

    // ---------- Grading (rules 16, 17) ----------

    public function test_rule_16_grade_0_to_20_with_decimals_and_optional_comment(): void
    {
        $assignment = $this->assignment();
        $submission = $assignment->submissions()->create(['student_id' => $this->student->id, 'body' => 'Rendu', 'submitted_at' => now()]);
        $url = route('teacher.assignments.grade', ['assignment' => $assignment->id, 'submission' => $submission->id]);

        $this->actingAs($this->teacher)->put($url, ['grade' => '21'])->assertSessionHasErrors(['grade' => 'La note est comprise entre 0 et 20.']);
        $this->actingAs($this->teacher)->put($url, ['grade' => '12,555'])->assertSessionHasErrors('grade');
        $this->actingAs($this->teacher)->put($url, ['grade' => '12,5', 'comment' => 'Bon travail'])->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame('12.50', $submission->grade);
        $this->assertSame('Bon travail', $submission->comment);
        $this->assertNotNull($submission->graded_at);
    }

    public function test_a_submission_of_another_homework_cannot_be_graded_through_this_one(): void
    {
        $mine = $this->assignment();
        $other = $this->assignment(['title' => 'Autre']);
        $submission = $other->submissions()->create(['student_id' => $this->student->id, 'body' => 'x', 'submitted_at' => now()]);

        $this->actingAs($this->teacher)->put(route('teacher.assignments.grade', ['assignment' => $mine->id, 'submission' => $submission->id]), ['grade' => 10])
            ->assertForbidden();
    }

    public function test_rule_17_homework_grades_never_change_the_quiz_average(): void
    {
        $assignment = $this->assignment();
        $assignment->submissions()->create(['student_id' => $this->student->id, 'body' => 'x', 'submitted_at' => now(), 'grade' => 4]);

        $this->actingAs($this->student)->get(route('student.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.average', null));
    }

    // ---------- States, visibility, rule 18 ----------

    public function test_the_teacher_sees_every_state(): void
    {
        $assignment = $this->assignment(['due_at' => now()->subHour(), 'allow_late' => false]);
        $absent = User::factory()->student()->create();
        $this->group->enrollments()->create(['student_id' => $absent->id, 'joined_at' => now()]);
        $assignment->submissions()->create(['student_id' => $this->student->id, 'body' => 'x', 'submitted_at' => now()->subDay(), 'grade' => 14]);

        $this->actingAs($this->teacher)->get(route('teacher.assignments.show', ['assignment' => $assignment->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teacher/assignments/Show')
                ->where('stats.expected', 2)
                ->where('stats.graded', 1)
                ->where('stats.average', 14)
                ->where('stats.missing', 1)
                ->has('rows', 2));
    }

    public function test_rule_18_a_removed_student_loses_access_but_the_submission_is_kept(): void
    {
        $assignment = $this->assignment();
        $this->submit($assignment, ['body' => 'Mon travail']);
        $this->group->enrollments()->where('student_id', $this->student->id)->update(['removed_at' => now()]);

        $this->actingAs($this->student)->get(route('student.assignments.index'))->assertInertia(fn (Assert $page) => $page->has('assignments', 0));
        $this->actingAs($this->student)->get(route('student.assignments.show', ['assignment' => $assignment->id]))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('teacher.assignments.show', ['assignment' => $assignment->id]))
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.removed', true)->where('rows.0.submission.body', 'Mon travail'));
    }

    public function test_a_homework_with_submissions_cannot_be_deleted(): void
    {
        $assignment = $this->assignment();
        $this->submit($assignment, ['body' => 'Travail']);

        $this->actingAs($this->teacher)->delete(route('teacher.assignments.destroy', ['assignment' => $assignment->id]))->assertSessionHas('error');
        $this->assertNotNull($assignment->fresh());
    }

    public function test_only_the_author_and_the_teacher_download_a_submitted_file(): void
    {
        $assignment = $this->assignment();
        $this->submit($assignment, ['file' => UploadedFile::fake()->create('copie.pdf', 50, 'application/pdf')]);
        $submission = Submission::firstOrFail();
        $classmate = User::factory()->student()->create();
        $this->group->enrollments()->create(['student_id' => $classmate->id, 'joined_at' => now()]);

        $this->actingAs($this->student)->get(route('files.link', ['kind' => 'rendu', 'id' => $submission->id]))->assertRedirect();
        $this->actingAs($this->teacher)->get(route('files.link', ['kind' => 'rendu', 'id' => $submission->id]))->assertRedirect();
        $this->actingAs($classmate)->get(route('files.link', ['kind' => 'rendu', 'id' => $submission->id]))->assertForbidden();
    }

    public function test_the_material_of_a_homework_can_be_downloaded_even_if_not_published_in_the_group(): void
    {
        $material = Material::create(['teacher_id' => $this->teacher->id, 'title' => 'Cours', 'type' => 'course', 'url' => 'https://exemple.dj', 'file_path' => 'x.pdf', 'file_name' => 'x.pdf']);
        $this->assignment(['material_id' => $material->id]);

        $this->actingAs($this->student)->get(route('files.link', ['kind' => 'support', 'id' => $material->id]))->assertRedirect();
    }
}
