<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Notion;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Group $td1;

    private Group $td2;

    private Notion $boucles;

    private Notion $tableaux;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->td1 = Group::factory()->create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique']);
        $this->td2 = Group::factory()->create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique']);
        $this->boucles = Notion::create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique', 'name' => 'Boucles']);
        $this->tableaux = Notion::create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique', 'name' => 'Tableaux']);
    }

    private function question(int $notionId, int $correct = 1, string $text = 'Combien de tours ?'): array
    {
        return ['text' => $text, 'notion_id' => $notionId, 'choices' => ['3', '4', '5', '6'], 'correct' => $correct];
    }

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Quiz 1 – Boucles',
            'description' => null,
            'subject' => 'Algorithmique',
            'level' => 'L1',
            'duration_minutes' => 15,
            'due_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'corrections_at' => now()->addWeek()->addDay()->format('Y-m-d\TH:i'),
            'group_ids' => [$this->td1->id, $this->td2->id],
            'publish' => true,
            'questions' => [
                $this->question($this->boucles->id, 0, 'Q1'),
                $this->question($this->boucles->id, 1, 'Q2'),
                $this->question($this->tableaux->id, 2, 'Q3'),
                $this->question($this->tableaux->id, 3, 'Q4'),
                $this->question($this->boucles->id, 1, 'Q5'),
            ],
            ...$overrides,
        ];
    }

    private function attempt(Quiz $quiz): void
    {
        Attempt::create([
            'quiz_id' => $quiz->id, 'student_id' => User::factory()->student()->create()->id,
            'status' => AttemptStatus::Submitted, 'started_at' => now(), 'score' => 10,
        ]);
    }

    // ---------- Creation ----------

    public function test_acceptance_teacher_creates_and_assigns_a_5_question_quiz(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teacher.quizzes.index'));

        $quiz = Quiz::with('questions.choices')->sole();
        $this->assertTrue($quiz->isPublished());
        $this->assertSame(15, $quiz->duration_minutes);
        $this->assertCount(5, $quiz->questions);

        // Rule 2: one quiz, several groups.
        $this->assertEqualsCanonicalizing([$this->td1->id, $this->td2->id], $quiz->groups()->pluck('groups.id')->all());

        // Rules 4 and 5: 4 choices, one correct, a notion per question, in order.
        foreach ($quiz->questions as $i => $question) {
            $this->assertSame('Q'.($i + 1), $question->text);
            $this->assertCount(4, $question->choices);
            $this->assertSame(1, $question->choices->where('is_correct', true)->count());
            $this->assertNotNull($question->notion_id);
        }
        $this->assertTrue($quiz->questions[2]->choices[2]->is_correct);
    }

    public function test_a_draft_needs_no_group_and_is_not_published(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.quizzes.store'), $this->payload(['publish' => false, 'group_ids' => []]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Quiz::sole()->published_at);
    }

    public function test_publishing_requires_a_group_and_a_future_due_date(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.quizzes.store'), $this->payload([
                'group_ids' => [],
                'due_at' => now()->subDay()->format('Y-m-d\TH:i'),
                'corrections_at' => now()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors(['group_ids', 'due_at']);

        $this->assertDatabaseCount('quizzes', 0);
    }

    // ---------- Rule 4 / 5 validation ----------

    public function test_rule_4_exactly_four_choices(): void
    {
        $bad = $this->question($this->boucles->id);
        $bad['choices'] = ['a', 'b', 'c'];

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload(['questions' => [$bad]]))
            ->assertSessionHasErrors(['questions.0.choices' => 'Chaque question doit avoir exactement 4 propositions.']);
    }

    public function test_rule_4_choices_cannot_be_empty_and_one_correct_answer_is_required(): void
    {
        $bad = $this->question($this->boucles->id);
        $bad['choices'][2] = '';
        $bad['correct'] = 4;

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload(['questions' => [$bad]]))
            ->assertSessionHasErrors(['questions.0.choices.2', 'questions.0.correct']);
    }

    public function test_rule_5_each_question_needs_one_of_my_notions(): void
    {
        $foreign = Notion::create(['teacher_id' => User::factory()->teacher()->create()->id, 'subject' => 'Algorithmique', 'name' => 'Autre']);

        $this->actingAs($this->teacher)
            ->post(route('teacher.quizzes.store'), $this->payload(['questions' => [
                ['text' => 'Q', 'notion_id' => null, 'choices' => ['a', 'b', 'c', 'd'], 'correct' => 0],
                $this->question($foreign->id),
            ]]))
            ->assertSessionHasErrors(['questions.0.notion_id', 'questions.1.notion_id']);
    }

    public function test_quiz_cannot_target_another_teachers_group(): void
    {
        $foreignGroup = Group::factory()->create();

        $this->actingAs($this->teacher)
            ->post(route('teacher.quizzes.store'), $this->payload(['group_ids' => [$foreignGroup->id]]))
            ->assertSessionHasErrors('group_ids.0');
    }

    public function test_corrections_cannot_be_published_before_the_due_date(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.quizzes.store'), $this->payload([
                'due_at' => now()->addWeek()->format('Y-m-d\TH:i'),
                'corrections_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors(['corrections_at' => 'Les corrections doivent être publiées après la date limite.']);
    }

    // ---------- Editing ----------

    public function test_update_replaces_questions_while_nobody_has_answered(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload());
        $quiz = Quiz::sole();

        $this->actingAs($this->teacher)
            ->put(route('teacher.quizzes.update', $quiz), $this->payload(['questions' => [$this->question($this->tableaux->id, 3, 'Nouvelle')], 'group_ids' => [$this->td1->id]]))
            ->assertSessionHasNoErrors();

        $quiz->refresh();
        $this->assertSame(['Nouvelle'], $quiz->questions()->pluck('text')->all());
        $this->assertSame([$this->td1->id], $quiz->groups()->pluck('groups.id')->all());
    }

    public function test_questions_are_locked_once_a_student_has_answered_but_dates_and_groups_stay_editable(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload());
        $quiz = Quiz::sole();
        $this->attempt($quiz);
        $newDue = now()->addWeeks(2)->format('Y-m-d\TH:i');

        $this->actingAs($this->teacher)
            ->put(route('teacher.quizzes.update', $quiz), $this->payload([
                'due_at' => $newDue,
                'corrections_at' => now()->addWeeks(3)->format('Y-m-d\TH:i'),
                'group_ids' => [$this->td2->id],
                'questions' => [$this->question($this->tableaux->id, 0, 'Tentative de modification')],
                'publish' => false, // cannot go back to draft once answered
            ]))
            ->assertSessionHasNoErrors();

        $quiz->refresh();
        $this->assertSame(5, $quiz->questions()->count());
        $this->assertSame($newDue, $quiz->due_at->format('Y-m-d\TH:i'));
        $this->assertSame([$this->td2->id], $quiz->groups()->pluck('groups.id')->all());
        $this->assertNotNull($quiz->published_at);

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.edit', $quiz))
            ->assertInertia(fn (Assert $page) => $page->where('locked', true)->where('attemptsCount', 1));
    }

    public function test_edit_page_gives_the_owner_the_correct_answers(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload());

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.edit', Quiz::sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->component('teacher/quizzes/Edit')
                ->has('quiz.questions', 5)
                ->where('quiz.questions.2.correct', 2)
                ->has('notions', 2)
                ->has('groups', 2));
    }

    // ---------- Delete / duplicate ----------

    public function test_a_quiz_already_answered_cannot_be_deleted(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload());
        $quiz = Quiz::sole();
        $this->attempt($quiz);

        $this->actingAs($this->teacher)->delete(route('teacher.quizzes.destroy', $quiz))->assertSessionHas('error');
        $this->assertModelExists($quiz);
    }

    public function test_an_unanswered_quiz_can_be_deleted(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload());
        $quiz = Quiz::sole();

        $this->actingAs($this->teacher)->delete(route('teacher.quizzes.destroy', $quiz))->assertSessionHas('success');
        $this->assertModelMissing($quiz);
        $this->assertDatabaseCount('choices', 0);
    }

    public function test_duplicate_creates_a_draft_copy_with_the_same_questions(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload());
        $original = Quiz::sole();
        $this->attempt($original);

        $this->actingAs($this->teacher)->post(route('teacher.quizzes.duplicate', $original))->assertSessionHas('success');

        $copy = Quiz::where('id', '!=', $original->id)->with('questions.choices')->sole();
        $this->assertSame('Quiz 1 – Boucles (copie)', $copy->title);
        $this->assertNull($copy->published_at);
        $this->assertCount(5, $copy->questions);
        $this->assertSame(5, $copy->questions->flatMap->choices->where('is_correct', true)->count());
        $this->assertSame(0, $copy->attempts()->count());
        $this->assertSame(2, $copy->groups()->count());
    }

    // ---------- Rule 9: ownership ----------

    public function test_another_teacher_cannot_edit_delete_or_duplicate_my_quiz(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload());
        $quiz = Quiz::sole();
        $intruder = User::factory()->teacher()->create();

        $this->actingAs($intruder)->get(route('teacher.quizzes.edit', $quiz))->assertForbidden();
        $this->actingAs($intruder)->put(route('teacher.quizzes.update', $quiz), $this->payload(['group_ids' => []]))->assertForbidden();
        $this->actingAs($intruder)->delete(route('teacher.quizzes.destroy', $quiz))->assertForbidden();
        $this->actingAs($intruder)->post(route('teacher.quizzes.duplicate', $quiz))->assertForbidden();

        $this->actingAs($intruder)->get(route('teacher.quizzes.index'))->assertInertia(fn (Assert $page) => $page->has('quizzes', 0));
    }

    // ---------- Notions ----------

    public function test_teacher_manages_notions_and_names_are_unique_per_subject(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.notions.store'), ['subject' => 'Algorithmique', 'name' => 'Récursivité'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)
            ->post(route('teacher.notions.store'), ['subject' => 'Algorithmique', 'name' => 'Boucles'])
            ->assertSessionHasErrors(['name' => 'Cette notion existe déjà pour cette matière.']);

        // Same name, other subject: allowed (reusable per subject).
        $this->actingAs($this->teacher)
            ->post(route('teacher.notions.store'), ['subject' => 'Bases de données', 'name' => 'Boucles'])
            ->assertSessionHasNoErrors();

        $notion = Notion::firstWhere('name', 'Récursivité');
        $this->actingAs($this->teacher)->put(route('teacher.notions.update', $notion), ['subject' => 'Algorithmique', 'name' => 'Récursion'])->assertSessionHasNoErrors();
        $this->assertSame('Récursion', $notion->fresh()->name);

        $this->actingAs($this->teacher)->delete(route('teacher.notions.destroy', $notion))->assertSessionHas('success');
        $this->assertModelMissing($notion);
    }

    public function test_a_notion_used_by_a_question_cannot_be_deleted(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.quizzes.store'), $this->payload());

        $this->actingAs($this->teacher)->delete(route('teacher.notions.destroy', $this->boucles))->assertSessionHas('error');
        $this->assertModelExists($this->boucles);
    }

    public function test_another_teacher_cannot_touch_my_notions(): void
    {
        $intruder = User::factory()->teacher()->create();

        $this->actingAs($intruder)->put(route('teacher.notions.update', $this->boucles), ['subject' => 'X', 'name' => 'Y'])->assertForbidden();
        $this->actingAs($intruder)->delete(route('teacher.notions.destroy', $this->boucles))->assertForbidden();
        $this->actingAs($intruder)->get(route('teacher.notions.index'))->assertInertia(fn (Assert $page) => $page->has('notions', 0));
    }
}
