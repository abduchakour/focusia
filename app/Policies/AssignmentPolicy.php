<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

/**
 * Rule 9: a teacher only edits, deletes and grades their own homework.
 * Rules 12 and 18: a student only sees homework published in one of their current groups.
 */
class AssignmentPolicy
{
    public function manage(User $user, Assignment $assignment): bool
    {
        return $user->isTeacher() && $assignment->teacher_id === $user->id;
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return $user->isStudent() && $assignment->isVisibleTo($user);
    }
}
