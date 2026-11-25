<?php

namespace App\Models;

use App\Enums\Level;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of students followed by one teacher in one subject
 * (e.g. "L1 Informatique, Algorithmique, TD2").
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected $fillable = ['name', 'subject', 'level', 'access_code', 'teacher_id', 'institution_id'];

    protected static function booted(): void
    {
        static::creating(function (Group $group): void {
            $group->institution_id ??= Institution::DEFAULT_ID;
            $group->access_code ??= self::generateAccessCode();
        });
    }

    protected function casts(): array
    {
        return ['level' => Level::class];
    }

    /** Random 6-character code, unique among groups. */
    public static function generateAccessCode(): string
    {
        $alphabet = config('focusia.access_code_alphabet');

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('access_code', $code)->exists());

        return $code;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Current members (removed students excluded). */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments', 'group_id', 'student_id')
            ->withPivot(['joined_at', 'removed_at'])
            ->wherePivotNull('removed_at');
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class)->withPivot('created_at');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class)->withPivot('created_at');
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(Assignment::class)->withPivot('created_at');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class)->withPivot('created_at');
    }
}
