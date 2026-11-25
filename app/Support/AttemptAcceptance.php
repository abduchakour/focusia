<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Rule 8 + decision 2026-11-25: answers given offline are accepted if they were
 * given before the due date, even when they reach the server later.
 * Device clocks can be wrong, so the check relies on server times where possible:
 *  (a) started_at (server time, quiz downloaded) is before due_at;
 *  (b) started_at <= answered_at <= due_at (a small grace absorbs clock drift before the start);
 *  (c) with a duration: answered_at - started_at <= duration + grace.
 * Pure function, no database access.
 */
final class AttemptAcceptance
{
    public const NOT_STARTED_IN_TIME = 'not_started_in_time';

    public const AFTER_DUE = 'after_due';

    public const BEFORE_START = 'before_start';

    public const TOO_LONG = 'too_long';

    /** @return string|null null when accepted, otherwise one of the reason constants */
    public static function refusal(
        CarbonInterface $startedAt,
        CarbonInterface $answeredAt,
        CarbonInterface $dueAt,
        ?int $durationMinutes,
        int $graceSeconds,
    ): ?string {
        if ($startedAt->gte($dueAt)) {
            return self::NOT_STARTED_IN_TIME;
        }
        if ($answeredAt->gt($dueAt)) {
            return self::AFTER_DUE;
        }
        if ($answeredAt->lt($startedAt->subSeconds($graceSeconds))) {
            return self::BEFORE_START;
        }
        if ($durationMinutes !== null
            && $startedAt->diffInSeconds($answeredAt) > $durationMinutes * 60 + $graceSeconds) {
            return self::TOO_LONG;
        }

        return null;
    }

    /** French explanation shown to the student. */
    public static function message(string $reason): string
    {
        return match ($reason) {
            self::NOT_STARTED_IN_TIME => "Ce quiz n'a pas été commencé avant la date limite.",
            self::AFTER_DUE => 'Vos réponses ont été données après la date limite : elles ne peuvent pas être acceptées.',
            self::BEFORE_START => "L'heure de votre téléphone semble incorrecte : réglez-la puis réessayez.",
            self::TOO_LONG => 'Vos réponses ont été données après la fin du temps imparti : elles ne peuvent pas être acceptées.',
            default => 'Vos réponses ne peuvent pas être acceptées.',
        };
    }
}
