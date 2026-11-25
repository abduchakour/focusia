<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

/**
 * Rule 9: a teacher only manages their own groups.
 * (Students never reach these routes: blocked earlier by the role middleware.)
 */
class GroupPolicy
{
    public function manage(User $user, Group $group): bool
    {
        return $user->isTeacher() && $group->teacher_id === $user->id;
    }
}
