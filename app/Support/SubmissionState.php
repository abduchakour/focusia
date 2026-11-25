<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use Carbon\CarbonInterface;

/**
 * Derives the per-student homework state. Nothing is stored, so NON_RENDU is
 * always accurate without any scheduled job.
 */
final class SubmissionState
{
    public static function for(Assignment $assignment, ?Submission $submission, ?CarbonInterface $now = null): SubmissionStatus
    {
        $now ??= now();

        if ($submission !== null) {
            if ($submission->grade !== null) {
                return SubmissionStatus::Graded;
            }

            return $submission->is_late ? SubmissionStatus::Late : SubmissionStatus::Submitted;
        }

        if ($now->lte($assignment->due_at) || $assignment->allow_late) {
            return SubmissionStatus::ToSubmit;
        }

        return SubmissionStatus::Missing;
    }

    /**
     * Rule 15: can a (new or replacement) submission be accepted at $now?
     * Returns null when refused, otherwise whether it is late.
     */
    public static function acceptance(Assignment $assignment, ?CarbonInterface $now = null): ?bool
    {
        $now ??= now();

        if ($now->lte($assignment->due_at)) {
            return false; // on time
        }

        return $assignment->allow_late ? true : null;
    }
}
