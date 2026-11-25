<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property int $institution_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'institution_id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @var list<string> */
    protected $appends = ['name'];

    protected static function booted(): void
    {
        // V1 has a single institution: attach new users to it automatically.
        static::creating(function (User $user): void {
            $user->institution_id ??= Institution::DEFAULT_ID;
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /** Full name, used for display only. */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    public function isTeacher(): bool
    {
        return $this->role === Role::Teacher;
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** Groups owned by a teacher. */
    public function teachingGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'teacher_id');
    }

    /** Content owned by a teacher (rule 9: a teacher only edits their own content). */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'teacher_id');
    }

    public function notions(): HasMany
    {
        return $this->hasMany(Notion::class, 'teacher_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'teacher_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'teacher_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'teacher_id');
    }

    /** All enrollments of a student, including the ones they were removed from. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    /** Groups a student currently belongs to (removed memberships excluded). */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'enrollments', 'student_id', 'group_id')
            ->withPivot(['joined_at', 'removed_at'])
            ->wherePivotNull('removed_at');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class, 'student_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'student_id');
    }
}
