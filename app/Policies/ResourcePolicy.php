<?php

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;

/**
 * Rule 9: a teacher only edits or deletes their own library resources.
 * Rules 12 and 18: a student only opens resources published in one of their current groups.
 */
class ResourcePolicy
{
    public function manage(User $user, Resource $resource): bool
    {
        return $user->isTeacher() && $resource->teacher_id === $user->id;
    }

    public function view(User $user, Resource $resource): bool
    {
        return $this->manage($user, $resource) || ($user->isStudent() && $resource->isVisibleTo($user));
    }
}
