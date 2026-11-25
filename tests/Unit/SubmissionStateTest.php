<?php

namespace Tests\Unit;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Support\SubmissionState;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SubmissionStateTest extends TestCase
{
    private CarbonImmutable $due;

    protected function setUp(): void
    {
        parent::setUp();
        $this->due = CarbonImmutable::parse('2026-12-01 18:00');
    }

    private function assignment(bool $allowLate): Assignment
    {
        return new Assignment(['due_at' => $this->due, 'allow_late' => $allowLate]);
    }

    public function test_to_submit_before_due_date(): void
    {
        $state = SubmissionState::for($this->assignment(false), null, $this->due->subHour());
        $this->assertSame(SubmissionStatus::ToSubmit, $state);
    }

    public function test_missing_after_due_date_without_late_allowed(): void
    {
        $state = SubmissionState::for($this->assignment(false), null, $this->due->addMinute());
        $this->assertSame(SubmissionStatus::Missing, $state);
    }

    public function test_still_to_submit_after_due_date_when_late_allowed(): void
    {
        $state = SubmissionState::for($this->assignment(true), null, $this->due->addDay());
        $this->assertSame(SubmissionStatus::ToSubmit, $state);
    }

    public function test_submitted_late_and_graded(): void
    {
        $assignment = $this->assignment(true);

        $onTime = new Submission(['is_late' => false]);
        $late = new Submission(['is_late' => true]);
        $graded = new Submission(['is_late' => true, 'grade' => 14.5]);

        $this->assertSame(SubmissionStatus::Submitted, SubmissionState::for($assignment, $onTime, $this->due->addDay()));
        $this->assertSame(SubmissionStatus::Late, SubmissionState::for($assignment, $late, $this->due->addDay()));
        $this->assertSame(SubmissionStatus::Graded, SubmissionState::for($assignment, $graded, $this->due->addDay()));
    }

    public function test_acceptance_rule_15(): void
    {
        $this->assertFalse(SubmissionState::acceptance($this->assignment(false), $this->due));            // on time (inclusive)
        $this->assertNull(SubmissionState::acceptance($this->assignment(false), $this->due->addSecond())); // refused
        $this->assertTrue(SubmissionState::acceptance($this->assignment(true), $this->due->addSecond()));  // accepted, late
    }
}
