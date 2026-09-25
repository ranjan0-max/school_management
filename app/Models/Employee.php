<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Enums\Gender;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher or staff member. `user_id` links an optional login account.
 *
 * @property int $school_id
 * @property int|null $user_id
 * @property string $employee_no
 * @property EmployeeType $type
 * @property string $name
 * @property Gender|null $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property CarbonImmutable|null $joining_date
 * @property EmployeeStatus $status
 */
#[Fillable([
    'school_id',
    'user_id',
    'employee_no',
    'type',
    'name',
    'gender',
    'date_of_birth',
    'phone',
    'email',
    'designation',
    'qualification',
    'joining_date',
    'address',
    'photo_path',
    'status',
])]
class Employee extends Model
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EmployeeType::class,
            'gender' => Gender::class,
            'status' => EmployeeStatus::class,
            'date_of_birth' => 'date',
            'joining_date' => 'date',
        ];
    }
}
