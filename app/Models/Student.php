<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $school_id
 * @property int|null $user_id
 * @property string $admission_no
 * @property string $first_name
 * @property string|null $last_name
 * @property Gender|null $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property CarbonImmutable|null $admission_date
 * @property StudentStatus $status
 */
#[Fillable([
    'school_id',
    'user_id',
    'admission_no',
    'first_name',
    'last_name',
    'gender',
    'date_of_birth',
    'blood_group',
    'phone',
    'email',
    'address',
    'photo_path',
    'admission_date',
    'status',
])]
class Student extends Model
{
    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot(['school_id', 'relation', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }
}
