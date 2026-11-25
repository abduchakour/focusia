<?php

namespace Tests\Unit;

use App\Support\Grading;
use InvalidArgumentException;
use Tests\TestCase;

class GradingTest extends TestCase
{
    public function test_quiz_score_examples_from_the_specification(): void
    {
        $this->assertSame(12.0, Grading::quizScore(6, 10));
        $this->assertSame(14.4, Grading::quizScore(18, 25));
    }

    public function test_quiz_score_bounds_and_rounding(): void
    {
        $this->assertSame(0.0, Grading::quizScore(0, 7));
        $this->assertSame(20.0, Grading::quizScore(7, 7));
        $this->assertSame(6.67, Grading::quizScore(1, 3));
    }

    public function test_quiz_score_rejects_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Grading::quizScore(11, 10);
    }

    public function test_quiz_score_rejects_empty_quiz(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Grading::quizScore(0, 0);
    }

    public function test_success_rate_per_notion(): void
    {
        $this->assertSame(0.75, Grading::successRate(3, 4));
        $this->assertNull(Grading::successRate(0, 0));
    }

    public function test_student_in_difficulty_rules(): void
    {
        config(['focusia.difficulty.average' => 10, 'focusia.difficulty.notion_rate' => 0.5]);

        $this->assertTrue(Grading::isInDifficulty(9.99, [0.9]));         // low average
        $this->assertTrue(Grading::isInDifficulty(15.0, [0.9, 0.49]));   // one weak notion
        $this->assertFalse(Grading::isInDifficulty(10.0, [0.5, 1.0]));   // thresholds are strict
        $this->assertFalse(Grading::isInDifficulty(null, [null]));       // no data
    }

    public function test_difficulty_thresholds_are_configurable(): void
    {
        config(['focusia.difficulty.average' => 12, 'focusia.difficulty.notion_rate' => 0.6]);

        $this->assertTrue(Grading::isInDifficulty(11.5, []));
        $this->assertTrue(Grading::isInDifficulty(14.0, [0.55]));
    }
}
