<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $school_id
 * @property int $academic_session_id
 * @property int $section_id
 * @property int $day
 * @property int $period_id
 * @property int|null $subject_id
 * @property int|null $teacher_id
 */
#[Fillable([
    'school_id',
    'academic_session_id',
    'section_id',
    'day',
    'period_id',
    'subject_id',
    'teacher_id',
])]
class TimetableEntry extends Model
{
    /**
     * Working days shown on the timetable (ISO numbers, 1 = Monday).
     * Add 7 => 'Sunday' here if a school ever needs it.
     */
    public const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Period, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'integer',
        ];
    }
}
