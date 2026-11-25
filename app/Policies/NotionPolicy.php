<?php

namespace App\Policies;

use App\Models\Notion;
use App\Models\User;

/** Rule 9: a teacher only manages their own notions. */
class NotionPolicy
{
    public function manage(User $user, Notion $notion): bool
    {
        return $user->isTeacher() && $notion->teacher_id === $user->id;
    }
}
