<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

/**
 * Rule 9: a teacher only edits or deletes their own materials.
 * Rules 12 and 18: a student only opens materials published in one of their current groups.
 */
class MaterialPolicy
{
    public function manage(User $user, Material $material): bool
    {
        return $user->isTeacher() && $material->teacher_id === $user->id;
    }

    public function view(User $user, Material $material): bool
    {
        if ($this->manage($user, $material)) {
            return true;
        }

        // Published in one of the student's groups, or linked to homework they can see (rule 13).
        return $user->isStudent() && (
            $material->isVisibleTo($user)
            || $material->assignments()->visibleTo($user)->exists()
        );
    }
}
