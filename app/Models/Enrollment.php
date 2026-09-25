<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's section and roll number for one academic session.
 *
 * @property int $school_id
 * @property int $student_id
 * @property int $academic_session_id
 * @property int $section_id
 * @property string|null $roll_no
 */
#[Fillable([
    'school_id',
    'student_id',
    'academic_session_id',
    'section_id',
    'roll_no',
])]
class Enrollment extends Model
{
    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

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
}
