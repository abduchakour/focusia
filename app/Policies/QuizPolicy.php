<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

/**
 * Rule 9: a teacher only manages their own quizzes.
 * Rules 12 and 18: a student only takes a published quiz of a group they still belong to.
 */
class QuizPolicy
{
    public function manage(User $user, Quiz $quiz): bool
    {
        return $user->isTeacher() && $quiz->teacher_id === $user->id;
    }

    public function take(User $user, Quiz $quiz): bool
    {
        return $user->isStudent() && Quiz::whereKey($quiz->id)->visibleTo($user)->exists();
    }
}
