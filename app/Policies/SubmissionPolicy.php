<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

/** A submission is seen by its author and by the teacher of the homework, nobody else. */
class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        return $submission->student_id === $user->id
            || ($user->isTeacher() && $submission->assignment()->where('teacher_id', $user->id)->exists());
    }
}
