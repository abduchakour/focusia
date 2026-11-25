<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's single submission for an assignment (rule 14): a file, a text, or both.
 *
 * @property Carbon $submitted_at
 * @property string|null $grade
 */
class Submission extends Model
{
    protected $fillable = [
        'assignment_id', 'student_id', 'file_path', 'file_name', 'mime_type', 'file_size',
        'body', 'submitted_at', 'is_late', 'grade', 'comment', 'graded_at',
    ];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
            'grade' => 'decimal:2',
            'file_size' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
