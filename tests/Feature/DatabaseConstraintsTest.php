<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\MaterialType;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Material;
use App\Models\Notion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The specification requires some rules to be enforced by the database itself,
 * not only by application code. These tests write directly to the tables.
 */
class DatabaseConstraintsTest extends TestCase
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

    private function quiz(): Quiz
    {
        return Quiz::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Quiz', 'subject' => 'Algorithmique', 'level' => 'L1',
            'due_at' => now()->addDay(), 'corrections_at' => now()->addDays(2), 'published_at' => now(),
        ]);
    }

    private function question(Quiz $quiz): Question
    {
        $notion = Notion::create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique', 'name' => 'Boucles '.uniqid()]);

        return Question::create(['quiz_id' => $quiz->id, 'notion_id' => $notion->id, 'text' => 'Q ?', 'position' => 0]);
    }

    public function test_rule_3_one_attempt_per_student_and_quiz(): void
    {
        $quiz = $this->quiz();
        $data = ['quiz_id' => $quiz->id, 'student_id' => $this->student->id, 'status' => AttemptStatus::Started, 'started_at' => now()];
        Attempt::create($data);

        $this->expectException(QueryException::class);
        Attempt::create($data);
    }

    public function test_rule_4_at_most_one_correct_choice(): void
    {
        $question = $this->question($this->quiz());
        $question->choices()->create(['text' => 'A', 'is_correct' => true, 'position' => 0]);
        $question->choices()->create(['text' => 'B', 'is_correct' => false, 'position' => 1]);

        $this->expectException(QueryException::class);
        $question->choices()->create(['text' => 'C', 'is_correct' => true, 'position' => 2]);
    }

    public function test_rule_4_at_most_four_choices(): void
    {
        $question = $this->question($this->quiz());
        foreach (range(0, 3) as $p) {
            $question->choices()->create(['text' => "C$p", 'is_correct' => $p === 0, 'position' => $p]);
        }

        $this->expectException(QueryException::class);
        $question->choices()->create(['text' => 'C4', 'is_correct' => false, 'position' => 4]);
    }

    public function test_rule_4_choice_positions_are_unique(): void
    {
        $question = $this->question($this->quiz());
        $question->choices()->create(['text' => 'A', 'is_correct' => true, 'position' => 0]);

        $this->expectException(QueryException::class);
        $question->choices()->create(['text' => 'B', 'is_correct' => false, 'position' => 0]);
    }

    public function test_rule_2_and_11_a_content_targets_a_group_only_once(): void
    {
        $group = Group::factory()->create(['teacher_id' => $this->teacher->id]);
        $quiz = $this->quiz();
        $quiz->groups()->attach($group->id);

        $this->expectException(QueryException::class);
        $quiz->groups()->attach($group->id);
    }

    public function test_rule_10_material_needs_a_file_or_a_link(): void
    {
        $this->expectException(QueryException::class);
        Material::create(['teacher_id' => $this->teacher->id, 'title' => 'Vide', 'type' => MaterialType::Course]);
    }

    public function test_rule_14_one_submission_per_student_and_assignment(): void
    {
        $assignment = Assignment::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Devoir', 'instructions' => '...', 'due_at' => now()->addDay(),
        ]);
        $data = ['assignment_id' => $assignment->id, 'student_id' => $this->student->id, 'body' => 'Réponse', 'submitted_at' => now()];
        Submission::create($data);

        $this->expectException(QueryException::class);
        Submission::create($data);
    }

    public function test_rule_14_submission_needs_a_file_or_a_text(): void
    {
        $assignment = Assignment::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Devoir', 'instructions' => '...', 'due_at' => now()->addDay(),
        ]);

        $this->expectException(QueryException::class);
        Submission::create(['assignment_id' => $assignment->id, 'student_id' => $this->student->id, 'submitted_at' => now()]);
    }

    public function test_rule_16_grade_is_between_0_and_20(): void
    {
        $assignment = Assignment::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Devoir', 'instructions' => '...', 'due_at' => now()->addDay(),
        ]);

        $this->expectException(QueryException::class);
        Submission::create([
            'assignment_id' => $assignment->id, 'student_id' => $this->student->id,
            'body' => 'Réponse', 'submitted_at' => now(), 'grade' => 20.5,
        ]);
    }

    public function test_rule_19_deleting_a_material_keeps_the_assignment(): void
    {
        $material = Material::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Cours', 'type' => MaterialType::Course, 'url' => 'https://example.org',
        ]);
        $assignment = Assignment::create([
            'teacher_id' => $this->teacher->id, 'material_id' => $material->id,
            'title' => 'Devoir', 'instructions' => '...', 'due_at' => now()->addDay(),
        ]);

        $material->delete();

        $this->assertModelExists($assignment);
        $this->assertNull($assignment->fresh()->material_id);
    }

    public function test_rule_18_removed_student_keeps_results_but_leaves_the_group(): void
    {
        $group = Group::factory()->create(['teacher_id' => $this->teacher->id]);
        $enrollment = $group->enrollments()->create(['student_id' => $this->student->id]);
        $quiz = $this->quiz();
        Attempt::create(['quiz_id' => $quiz->id, 'student_id' => $this->student->id, 'status' => AttemptStatus::Submitted, 'started_at' => now(), 'score' => 15]);

        $enrollment->update(['removed_at' => now()]);

        $this->assertCount(0, $this->student->groups()->get());
        $this->assertCount(0, $group->students()->get());
        $this->assertCount(1, $this->student->attempts()->get());
    }

    public function test_group_access_code_is_generated_with_6_characters(): void
    {
        $group = Group::factory()->create(['teacher_id' => $this->teacher->id]);

        $this->assertMatchesRegularExpression('/^[A-HJ-KM-NP-Z2-9]{6}$/', $group->access_code);
    }

    public function test_correct_flag_is_hidden_when_serialized(): void
    {
        $question = $this->question($this->quiz());
        $choice = $question->choices()->create(['text' => 'A', 'is_correct' => true, 'position' => 0]);

        $this->assertArrayNotHasKey('is_correct', $choice->toArray());
    }

    public function test_demo_seeder_runs(): void
    {
        Storage::fake(config('focusia.files_disk'));

        $this->seed(DemoSeeder::class);

        // setUp() already created one teacher and one student.
        $this->assertSame(2 + 1, User::where('role', 'teacher')->count());
        $this->assertSame(30 + 1, User::where('role', 'student')->count());
        $this->assertSame(3, Group::count());
        $this->assertSame(6, Notion::count());
        $this->assertSame(4, Quiz::whereHas('attempts')->count());
        $this->assertSame(3, Material::count());
        $this->assertSame(2, Assignment::count());
    }
}
