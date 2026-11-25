<?php

namespace Tests\Unit;

use App\Support\AttemptAcceptance;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** Rule 8: offline answers accepted if given before the due date, even if synced later. */
class AttemptAcceptanceTest extends TestCase
{
    private CarbonImmutable $start;

    private CarbonImmutable $due;

    protected function setUp(): void
    {
        parent::setUp();
        $this->start = CarbonImmutable::parse('2026-12-01 10:00:00');
        $this->due = CarbonImmutable::parse('2026-12-01 18:00:00');
    }

    private function refusal(CarbonImmutable $answeredAt, ?int $duration = null, ?CarbonImmutable $start = null): ?string
    {
        return AttemptAcceptance::refusal($start ?? $this->start, $answeredAt, $this->due, $duration, 60);
    }

    public function test_answers_given_before_the_due_date_are_accepted(): void
    {
        $this->assertNull($this->refusal($this->start->addMinutes(30)));
        $this->assertNull($this->refusal($this->due), 'exactly at the due date is still accepted');
    }

    public function test_answers_given_after_the_due_date_are_refused(): void
    {
        $this->assertSame(AttemptAcceptance::AFTER_DUE, $this->refusal($this->due->addSecond()));
    }

    public function test_a_quiz_downloaded_after_the_due_date_is_refused(): void
    {
        $this->assertSame(
            AttemptAcceptance::NOT_STARTED_IN_TIME,
            $this->refusal($this->due, null, $this->due),
        );
    }

    public function test_a_device_clock_set_before_the_start_is_refused_beyond_the_grace(): void
    {
        $this->assertNull($this->refusal($this->start->subSeconds(60)), 'small drift tolerated');
        $this->assertSame(AttemptAcceptance::BEFORE_START, $this->refusal($this->start->subSeconds(61)));
    }

    public function test_the_duration_is_enforced_with_the_grace(): void
    {
        $this->assertNull($this->refusal($this->start->addMinutes(15)->addSeconds(60), 15));
        $this->assertSame(AttemptAcceptance::TOO_LONG, $this->refusal($this->start->addMinutes(15)->addSeconds(61), 15));
    }

    public function test_every_reason_has_a_french_message(): void
    {
        foreach ([AttemptAcceptance::AFTER_DUE, AttemptAcceptance::BEFORE_START, AttemptAcceptance::TOO_LONG, AttemptAcceptance::NOT_STARTED_IN_TIME] as $reason) {
            $this->assertNotSame('', AttemptAcceptance::message($reason));
        }
    }
}
