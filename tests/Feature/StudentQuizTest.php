<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Notion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Phase 4: taking a quiz, server grading, hidden corrections, offline acceptance (rules 3, 5-8, 12, 18). */
class StudentQuizTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Group $td1;

    private Group $td2;

    private Notion $boucles;

    private Notion $tableaux;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-01 10:00:00');

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();
        $this->td1 = Group::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->td2 = Group::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->td1->enrollments()->create(['student_id' => $this->student->id, 'joined_at' => now()]);
        $this->td2->enrollments()->create(['student_id' => $this->student->id, 'joined_at' => now()]);
        $this->boucles = Notion::create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique', 'name' => 'Boucles']);
        $this->tableaux = Notion::create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique', 'name' => 'Tableaux']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A published quiz; the correct choice of every question is at position 1 ("B"). */
    private function quiz(int $questions = 10, array $attributes = [], ?array $groups = null): Quiz
    {
        $quiz = Quiz::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Quiz boucles', 'subject' => 'Algorithmique', 'level' => 'L1',
            'duration_minutes' => 15, 'due_at' => now()->addDay(), 'corrections_at' => now()->addDays(2),
            'published_at' => now()->subHour(), ...$attributes,
        ]);
        $quiz->groups()->attach(collect($groups ?? [$this->td1, $this->td2])->pluck('id'));

        for ($i = 0; $i < $questions; $i++) {
            $question = $quiz->questions()->create([
                'notion_id' => $i % 2 === 0 ? $this->boucles->id : $this->tableaux->id,
                'text' => "Question $i", 'position' => $i,
            ]);
            foreach (['A', 'B', 'C', 'D'] as $p => $text) {
                $question->choices()->create(['text' => "Choix $text", 'is_correct' => $p === 1, 'position' => $p]);
            }
        }

        return $quiz;
    }

    private function startedAttempt(Quiz $quiz, ?Carbon $startedAt = null): Attempt
    {
        return $quiz->attempts()->create([
            'student_id' => $this->student->id, 'status' => AttemptStatus::Started, 'started_at' => $startedAt ?? now(),
        ]);
    }

    /** @return array<int, int> question id => chosen choice id, with $correct right answers */
    private function answers(Quiz $quiz, int $correct): array
    {
        return $quiz->questions()->with('choices')->get()
            ->mapWithKeys(fn (Question $q, int $i) => [$q->id => $q->choices[$i < $correct ? 1 : 0]->id])
            ->all();
    }

    private function submit(Quiz $quiz, array $answers, ?Carbon $answeredAt = null)
    {
        return $this->actingAs($this->student)->postJson(route('student.quizzes.submit', ['quiz' => $quiz->id]), [
            'answers' => $answers,
            'answered_at' => ($answeredAt ?? now())->toIso8601String(),
        ]);
    }

    // ---------- Listing (rules 12, 18) ----------

    public function test_the_list_shows_each_quiz_once_even_when_published_in_two_of_my_groups(): void
    {
        $quiz = $this->quiz();
        $this->quiz(attributes: ['title' => 'Brouillon', 'published_at' => null]);
        $other = Group::factory()->create();
        $this->quiz(attributes: ['title' => 'Autre groupe'], groups: [$other]);

        $this->actingAs($this->student)->get(route('student.quizzes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/quizzes/Index')
                ->has('quizzes', 1)
                ->where('quizzes.0.id', $quiz->id)
                ->where('quizzes.0.status', 'todo')
                ->where('quizzes.0.questions_count', 10));
    }

    public function test_a_removed_student_no_longer_sees_nor_takes_the_quiz(): void
    {
        $quiz = $this->quiz(groups: [$this->td1]);
        $this->td1->enrollments()->where('student_id', $this->student->id)->update(['removed_at' => now()]);

        $this->actingAs($this->student)->get(route('student.quizzes.index'))
            ->assertInertia(fn (Assert $page) => $page->has('quizzes', 0));
        $this->actingAs($this->student)->post(route('student.quizzes.start', ['quiz' => $quiz->id]))->assertForbidden();
    }

    public function test_a_draft_or_a_quiz_of_another_group_cannot_be_started(): void
    {
        $draft = $this->quiz(attributes: ['published_at' => null]);
        $foreign = $this->quiz(groups: [Group::factory()->create()]);

        $this->actingAs($this->student)->post(route('student.quizzes.start', ['quiz' => $draft->id]))->assertForbidden();
        $this->actingAs($this->student)->post(route('student.quizzes.start', ['quiz' => $foreign->id]))->assertForbidden();
        $this->submit($foreign, [])->assertForbidden();
        $this->assertDatabaseCount('attempts', 0);
    }

    // ---------- Starting and taking ----------

    public function test_starting_records_the_server_time_and_only_once(): void
    {
        $quiz = $this->quiz();

        $this->actingAs($this->student)->post(route('student.quizzes.start', ['quiz' => $quiz->id]))
            ->assertRedirect(route('student.quizzes.take', ['quiz' => $quiz->id]));
        Carbon::setTestNow(now()->addMinutes(5));
        $this->actingAs($this->student)->post(route('student.quizzes.start', ['quiz' => $quiz->id]));

        $this->assertDatabaseCount('attempts', 1);
        $this->assertEquals('2026-12-01 10:00:00', $quiz->attempts()->first()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_a_quiz_cannot_be_started_after_its_due_date(): void
    {
        $quiz = $this->quiz(attributes: ['due_at' => now()->subMinute(), 'corrections_at' => now()->addDay()]);

        $this->actingAs($this->student)->from(route('student.quizzes.index'))
            ->post(route('student.quizzes.start', ['quiz' => $quiz->id]))
            ->assertRedirect(route('student.quizzes.index'))
            ->assertSessionHas('error', 'La date limite de ce quiz est dépassée.');
        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_rule_7_the_quiz_page_never_contains_the_correct_answers(): void
    {
        $quiz = $this->quiz(3);
        $this->startedAttempt($quiz);

        $response = $this->actingAs($this->student)->get(route('student.quizzes.take', ['quiz' => $quiz->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/quizzes/Take')
                ->has('questions', 3)
                ->has('questions.0.choices', 4)
                ->where('attempt.deadline', now()->addMinutes(15)->toIso8601String()));

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('correct_for_question', $response->getContent());
    }

    public function test_the_deadline_never_goes_past_the_due_date(): void
    {
        $quiz = $this->quiz(attributes: ['due_at' => now()->addMinutes(5)]);
        $this->startedAttempt($quiz);

        $this->actingAs($this->student)->get(route('student.quizzes.take', ['quiz' => $quiz->id]))
            ->assertInertia(fn (Assert $page) => $page->where('attempt.deadline', now()->addMinutes(5)->toIso8601String()));
    }

    // ---------- Submitting and grading (rules 3, 5, 6) ----------

    public function test_acceptance_6_correct_out_of_10_gives_12_out_of_20(): void
    {
        $quiz = $this->quiz(10);
        $this->startedAttempt($quiz);

        $this->submit($quiz, $this->answers($quiz, 6), now()->addMinutes(8))
            ->assertOk()
            ->assertJson(['score' => 12, 'correct' => 6, 'total' => 10])
            ->assertJsonMissing(['already' => true]);

        $attempt = $quiz->attempts()->first();
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertSame('12.00', $attempt->score);
        $this->assertDatabaseCount('attempt_answers', 10);
        $this->assertSame(6, $attempt->answers()->where('is_correct', true)->count());
    }

    public function test_18_correct_out_of_25_gives_14_4(): void
    {
        $quiz = $this->quiz(25, ['duration_minutes' => null]);
        $this->startedAttempt($quiz);

        $this->submit($quiz, $this->answers($quiz, 18))->assertJson(['score' => 14.4]);
    }

    public function test_an_unanswered_question_counts_as_wrong(): void
    {
        $quiz = $this->quiz(4);
        $this->startedAttempt($quiz);
        $answers = $this->answers($quiz, 4);
        $answers[array_key_last($answers)] = null;

        $this->submit($quiz, $answers)->assertJson(['score' => 15, 'correct' => 3, 'total' => 4]);
        $this->assertDatabaseHas('attempt_answers', ['question_id' => array_key_last($answers), 'choice_id' => null, 'is_correct' => false]);
    }

    public function test_rule_3_a_second_submission_keeps_the_first_result(): void
    {
        $quiz = $this->quiz(10);
        $this->startedAttempt($quiz);

        $this->submit($quiz, $this->answers($quiz, 6))->assertJson(['score' => 12]);
        // e.g. the sync queue retries after a lost response, or someone tries again with better answers
        $this->submit($quiz, $this->answers($quiz, 10))->assertOk()->assertJson(['score' => 12, 'already' => true]);

        $this->assertDatabaseCount('attempts', 1);
        $this->assertDatabaseCount('attempt_answers', 10);
    }

    public function test_submitting_without_starting_is_refused(): void
    {
        $quiz = $this->quiz();

        $this->submit($quiz, [])->assertStatus(409);
    }

    public function test_a_choice_of_another_question_is_refused(): void
    {
        $quiz = $this->quiz(2);
        $this->startedAttempt($quiz);
        [$first, $second] = $quiz->questions()->with('choices')->get();

        $this->submit($quiz, [$first->id => $second->choices[1]->id])->assertStatus(422)->assertJsonValidationErrors('answers');
        $this->submit($quiz, [999999 => null])->assertStatus(422)->assertJsonValidationErrors('answers');
        $this->assertSame(AttemptStatus::Started, $quiz->attempts()->first()->status);
    }

    // ---------- Offline acceptance (rule 8) ----------

    public function test_rule_8_answers_given_offline_before_the_due_date_are_accepted_when_synced_later(): void
    {
        $quiz = $this->quiz(10, ['due_at' => now()->addHour(), 'corrections_at' => now()->addHours(2)]);
        $this->startedAttempt($quiz);
        $answeredAt = now()->addMinutes(10);

        // The phone gets its connection back the next day.
        Carbon::setTestNow(now()->addDay());
        $this->submit($quiz, $this->answers($quiz, 5), $answeredAt)->assertOk()->assertJson(['score' => 10]);

        $attempt = $quiz->attempts()->first();
        $this->assertEquals($answeredAt, $attempt->answered_at);
        $this->assertEquals(now(), $attempt->synced_at);
    }

    public function test_rule_8_answers_given_after_the_due_date_are_refused(): void
    {
        $quiz = $this->quiz(10, ['duration_minutes' => null, 'due_at' => now()->addHour(), 'corrections_at' => now()->addHours(2)]);
        $this->startedAttempt($quiz);
        Carbon::setTestNow(now()->addHours(3));

        $this->submit($quiz, $this->answers($quiz, 10), now()->subHour())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answered_at' => 'après la date limite']);
        $this->assertSame(AttemptStatus::Started, $quiz->attempts()->first()->status);
    }

    public function test_answers_given_after_the_duration_are_refused(): void
    {
        $quiz = $this->quiz(10, ['duration_minutes' => 15]);
        $this->startedAttempt($quiz);
        $answeredAt = now()->addMinutes(20);
        Carbon::setTestNow(now()->addMinutes(30));

        $this->submit($quiz, $this->answers($quiz, 10), $answeredAt)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answered_at' => 'temps imparti']);
    }

    public function test_a_device_time_in_the_future_is_brought_back_to_the_server_time(): void
    {
        $quiz = $this->quiz(10, ['duration_minutes' => null]);
        $this->startedAttempt($quiz, now()->subMinutes(10));

        $this->submit($quiz, $this->answers($quiz, 10), now()->addYear())->assertOk();
        $this->assertEquals(now(), $quiz->attempts()->first()->answered_at);
    }

    // ---------- Result and corrections (rule 7) ----------

    public function test_rule_7_before_corrections_at_the_result_shows_the_grade_only(): void
    {
        $quiz = $this->quiz(10);
        $this->startedAttempt($quiz);
        $this->submit($quiz, $this->answers($quiz, 6));

        $response = $this->actingAs($this->student)->get(route('student.quizzes.result', ['quiz' => $quiz->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/quizzes/Result')
                ->where('attempt.score', 12)
                ->where('corrections', null));

        $this->assertStringNotContainsString('is_correct', $response->getContent());
    }

    public function test_rule_7_after_corrections_at_the_corrections_are_shown(): void
    {
        $quiz = $this->quiz(4);
        $this->startedAttempt($quiz);
        $this->submit($quiz, $this->answers($quiz, 3));
        Carbon::setTestNow(now()->addDays(3));

        $this->actingAs($this->student)->get(route('student.quizzes.result', ['quiz' => $quiz->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('corrections.questions', 4)
                ->where('corrections.questions.0.is_correct', true)
                ->where('corrections.questions.3.is_correct', false)
                ->where('corrections.questions.3.choices.1.is_correct', true)
                ->where('corrections.notions.0.name', 'Boucles')
                ->where('corrections.notions.0.correct', 2)
                ->where('corrections.notions.1.correct', 1));
    }

    public function test_the_list_shows_the_grade_after_submission(): void
    {
        $quiz = $this->quiz(10);
        $this->startedAttempt($quiz);
        $this->submit($quiz, $this->answers($quiz, 6));

        $this->actingAs($this->student)->get(route('student.quizzes.index'))
            ->assertInertia(fn (Assert $page) => $page->where('quizzes.0.status', 'done')->where('quizzes.0.score', 12));
        $this->actingAs($this->student)->get(route('student.quizzes.take', ['quiz' => $quiz->id]))
            ->assertRedirect(route('student.quizzes.result', ['quiz' => $quiz->id]));
    }

    public function test_a_student_never_sees_the_result_of_another_student(): void
    {
        $quiz = $this->quiz(4);
        $this->startedAttempt($quiz);
        $this->submit($quiz, $this->answers($quiz, 4));

        $classmate = User::factory()->student()->create();
        $this->td1->enrollments()->create(['student_id' => $classmate->id, 'joined_at' => now()]);

        // The result page is always the one of the logged-in student: none for the classmate.
        $this->actingAs($classmate)->get(route('student.quizzes.result', ['quiz' => $quiz->id]))
            ->assertRedirect(route('student.quizzes.index'));
    }
}
