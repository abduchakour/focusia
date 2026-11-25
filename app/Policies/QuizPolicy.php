<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

/** Rule 9: a teacher only manages their own quizzes. */
class QuizPolicy
{
    public function manage(User $user, Quiz $quiz): bool
    {
        return $user->isTeacher() && $quiz->teacher_id === $user->id;
    }
}
