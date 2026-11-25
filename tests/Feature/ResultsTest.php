<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Models\Group;
use App\Models\Notion;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Insights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Phase 5: results, per-notion understanding, students in difficulty (rules 9, 17, 18 and the formulas). */
class ResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Group $group;

    private Notion $boucles;

    private Notion $tableaux;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->group = Group::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->boucles = Notion::create(['teacher_id' => $this->teacher->id, 'subject' => 'Algo', 'name' => 'Boucles']);
        $this->tableaux = Notion::create(['teacher_id' => $this->teacher->id, 'subject' => 'Algo', 'name' => 'Tableaux']);
    }

    private function student(?Group $group = null): User
    {
        $student = User::factory()->student()->create();
        ($group ?? $this->group)->enrollments()->create(['student_id' => $student->id, 'joined_at' => now()]);

        return $student;
    }

    /** 4 questions: 2 on Boucles, then 2 on Tableaux; the correct choice is at position 1. */
    private function quiz(array $attributes = []): Quiz
    {
        $quiz = Quiz::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Quiz', 'subject' => 'Algo', 'level' => 'L1',
            'due_at' => now()->subDay(), 'corrections_at' => now()->subHour(), 'published_at' => now()->subWeek(), ...$attributes,
        ]);
        $quiz->groups()->attach($this->group->id);
        foreach ([$this->boucles, $this->boucles, $this->tableaux, $this->tableaux] as $i => $notion) {
            $question = $quiz->questions()->create(['notion_id' => $notion->id, 'text' => "Q$i", 'position' => $i]);
            foreach (range(0, 3) as $p) {
                $question->choices()->create(['text' => "C$p", 'is_correct' => $p === 1, 'position' => $p]);
            }
        }

        return $quiz;
    }

    /**
     * A submitted attempt. $answers: one entry per question: true = right, false = wrong, null = blank.
     *
     * @param  list<bool|null>  $answers
     */
    private function answer(Quiz $quiz, User $student, array $answers): void
    {
        $correct = count(array_filter($answers, fn ($a) => $a === true));
        $attempt = $quiz->attempts()->create([
            'student_id' => $student->id, 'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(2), 'answered_at' => now()->subDays(2), 'synced_at' => now()->subDays(2),
            'correct_count' => $correct, 'total_count' => 4, 'score' => $correct / 4 * 20,
        ]);
        foreach ($quiz->questions()->with('choices')->get() as $i => $question) {
            $attempt->answers()->create([
                'question_id' => $question->id,
                'choice_id' => $answers[$i] === null ? null : $question->choices[$answers[$i] ? 1 : 2]->id,
                'is_correct' => $answers[$i] === true,
            ]);
        }
    }

    public function test_notion_rate_counts_only_the_answers_given(): void
    {
        $quiz = $this->quiz();
        // Boucles: 1 right + 1 blank => 1/1 = 100 %; Tableaux: 1 right, 1 wrong => 50 %.
        $this->answer($quiz, $this->student(), [true, null, true, false]);

        $rates = app(Insights::class)->notionRates([$quiz->id])->keyBy('name');

        $this->assertSame(1.0, $rates['Boucles']->rate);
        $this->assertSame(1, $rates['Boucles']->answered);
        $this->assertSame(0.5, $rates['Tableaux']->rate);
    }

    public function test_acceptance_the_teacher_sees_the_results_of_a_quiz(): void
    {
        $quiz = $this->quiz();
        $ali = $this->student();
        $sara = $this->student();
        $this->student(); // has not answered
        $this->answer($quiz, $ali, [true, true, true, true]);   // 20
        $this->answer($quiz, $sara, [true, false, false, null]); // 5

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.results', ['quiz' => $quiz->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teacher/quizzes/Results')
                ->where('stats.submitted', 2)
                ->where('stats.expected', 3)
                ->where('stats.average', 12.5)
                ->where('stats.passed', 1)
                ->where('distribution.1.count', 1)   // 5 is in 4–8
                ->has('students', 2)
                ->where('students.0.name', $ali->name)
                ->has('missing', 1)
                ->where('notions.0.name', 'Boucles')
                ->where('notions.0.rate', 0.75)       // 3 right out of 4 given
                ->where('questions.3.blank', 1)
                ->where('questions.3.choices.1.count', 1)
                ->where('questions.3.choices.2.count', 0));
    }

    public function test_the_grade_distribution_puts_each_grade_in_its_range(): void
    {
        $quiz = $this->quiz();
        $this->answer($quiz, $this->student(), [true, false, false, false]); // 5   -> 4–8
        $this->answer($quiz, $this->student(), [true, true, false, false]);  // 10  -> 10–12
        $this->answer($quiz, $this->student(), [true, true, true, true]);    // 20  -> 16–20

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.results', ['quiz' => $quiz->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('distribution.0.count', 0)
                ->where('distribution.1.count', 1)
                ->where('distribution.3.count', 1)
                ->where('distribution.6.count', 1));
    }

    public function test_rule_9_another_teacher_cannot_see_the_results(): void
    {
        $quiz = $this->quiz();
        $intruder = User::factory()->teacher()->create();

        $this->actingAs($intruder)->get(route('teacher.quizzes.results', ['quiz' => $quiz->id]))->assertForbidden();
        $this->actingAs($intruder)->get(route('teacher.groups.insights', ['group' => $this->group->id]))->assertForbidden();
    }

    public function test_rule_18_a_removed_student_keeps_their_result_but_is_flagged(): void
    {
        $quiz = $this->quiz();
        $removed = $this->student();
        $this->answer($quiz, $removed, [true, true, false, false]);
        $this->group->enrollments()->where('student_id', $removed->id)->update(['removed_at' => now()]);

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.results', ['quiz' => $quiz->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students', 1)
                ->where('students.0.removed', true)
                ->where('stats.expected', 0));

        // The group follow-up only lists the current members.
        $this->actingAs($this->teacher)->get(route('teacher.groups.insights', ['group' => $this->group->id]))
            ->assertInertia(fn (Assert $page) => $page->has('students', 0));
    }

    public function test_the_results_can_be_filtered_by_group(): void
    {
        $quiz = $this->quiz();
        $other = Group::factory()->create(['teacher_id' => $this->teacher->id]);
        $quiz->groups()->attach($other->id);
        $this->answer($quiz, $this->student(), [true, true, true, true]);
        $this->answer($quiz, $this->student($other), [false, false, false, false]);

        $this->actingAs($this->teacher)->get(route('teacher.quizzes.results', ['quiz' => $quiz->id, 'groupe' => $other->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedGroup', $other->id)
                ->has('students', 1)
                ->where('stats.average', 0));
    }

    public function test_students_in_difficulty_are_flagged_with_their_reasons(): void
    {
        $quiz = $this->quiz();
        $good = $this->student();
        $weakAverage = $this->student();
        $weakNotion = $this->student();
        $this->answer($quiz, $good, [true, true, true, true]);          // 20, all notions 100 %
        $this->answer($quiz, $weakAverage, [false, false, true, false]); // 5
        $this->answer($quiz, $weakNotion, [false, false, true, true]);   // 10, Boucles 0 %

        $this->actingAs($this->teacher)->get(route('teacher.groups.insights', ['group' => $this->group->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teacher/groups/Insights')
                ->has('students', 3)
                ->where('students.0.id', $weakAverage->id)
                ->where('students.0.difficulty', true)
                ->where('students.0.reasons.0', 'Moyenne 5/20')
                ->where('students.1.id', $weakNotion->id)
                ->where('students.1.difficulty', true)
                ->where('students.1.reasons.0', 'Boucles 0 %')
                ->where('students.2.id', $good->id)
                ->where('students.2.difficulty', false)
                ->where('quizzes.0.submitted', 3));
    }

    public function test_rule_17_quiz_grades_are_never_mixed_with_homework_grades(): void
    {
        $quiz = $this->quiz();
        $student = $this->student();
        $this->answer($quiz, $student, [true, true, false, false]); // 10

        $this->actingAs($this->teacher)->get(route('teacher.groups.insights', ['group' => $this->group->id]))
            ->assertInertia(fn (Assert $page) => $page->where('students.0.average', 10));
    }

    public function test_the_teacher_dashboard_summarises_groups_and_weak_notions(): void
    {
        $quiz = $this->quiz();
        $this->answer($quiz, $this->student(), [false, false, true, true]);

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.difficulty', 1)
                ->where('weakNotions.0.name', 'Boucles')
                ->where('groups.0.difficulty_count', 1)
                ->where('recentQuizzes.0.submitted', 1)
                ->where('recentQuizzes.0.expected', 1));
    }

    public function test_rule_7_the_student_notion_view_waits_for_the_corrections(): void
    {
        $student = $this->student();
        $this->answer($this->quiz(), $student, [true, true, false, false]);
        $hidden = $this->quiz(['title' => 'Pas encore corrigé', 'due_at' => now()->addDay(), 'corrections_at' => now()->addDays(2)]);
        $this->answer($hidden, $student, [false, false, false, false]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('notions', 2)
                ->where('notions.0.name', 'Boucles')
                ->where('notions.0.rate', 1)
                ->where('notions.1.rate', 0));
    }
}
