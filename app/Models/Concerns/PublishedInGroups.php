<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Content published in groups (materials, library, homework).
 * Rule 12: a student sees it only if it is published in one of their groups, and only once.
 * Rule 18: a removed student no longer sees the content of that group.
 */
trait PublishedInGroups
{
    public function scopeVisibleTo(Builder $query, User $student): void
    {
        $query->whereHas('groups.enrollments', fn (Builder $enrollment) => $enrollment
            ->where('student_id', $student->id)
            ->whereNull('removed_at'));
    }

    public function isVisibleTo(User $student): bool
    {
        return static::whereKey($this->getKey())->visibleTo($student)->exists();
    }

    /** File extension in capitals for display ("PDF", "DOCX"), null without a file. */
    public function fileExtension(): ?string
    {
        return $this->file_name ? strtoupper(pathinfo($this->file_name, PATHINFO_EXTENSION)) : null;
    }
}
