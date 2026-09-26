<?php

namespace App\Models;

use App\Models\Concerns\IsAttendanceSheet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One section's attendance for one day.
 *
 * @property int $school_id
 * @property int $academic_session_id
 * @property int $section_id
 * @property CarbonImmutable $date
 */
#[Fillable([
    'school_id',
    'academic_session_id',
    'section_id',
    'date',
    'status',
    'taken_by',
    'approved_by',
    'approved_at',
])]
class StudentAttendanceSheet extends Model
{
    use IsAttendanceSheet;

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return HasMany<StudentAttendance, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'sheet_id');
    }
}
