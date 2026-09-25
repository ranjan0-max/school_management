<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $school_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 */
#[Fillable([
    'school_id',
    'user_id',
    'name',
    'phone',
    'email',
    'occupation',
    'address',
])]
class Guardian extends Model
{
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
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot(['school_id', 'relation', 'is_primary'])
            ->withTimestamps();
    }
}
