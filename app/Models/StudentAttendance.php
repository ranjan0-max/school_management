<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $school_id
 * @property int $sheet_id
 * @property int $student_id
 * @property AttendanceStatus $status
 * @property string|null $remark
 */
#[Fillable([
    'school_id',
    'sheet_id',
    'student_id',
    'date',
    'status',
    'remark',
])]
class StudentAttendance extends Model
{
    /**
     * @return BelongsTo<StudentAttendanceSheet, $this>
     */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(StudentAttendanceSheet::class, 'sheet_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }
}
