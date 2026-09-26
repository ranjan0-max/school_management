<?php

namespace App\Models;

use App\Models\Concerns\IsAttendanceSheet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The school's teacher and staff attendance for one day.
 *
 * @property int $school_id
 * @property CarbonImmutable $date
 */
#[Fillable([
    'school_id',
    'date',
    'status',
    'taken_by',
    'approved_by',
    'approved_at',
])]
class StaffAttendanceSheet extends Model
{
    use IsAttendanceSheet;

    /**
     * @return HasMany<StaffAttendance, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(StaffAttendance::class, 'sheet_id');
    }
}
