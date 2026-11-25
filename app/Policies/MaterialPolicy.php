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
        return $this->manage($user, $material) || ($user->isStudent() && $material->isVisibleTo($user));
    }
}
