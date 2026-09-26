<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $school_id
 * @property int $sheet_id
 * @property int $employee_id
 * @property AttendanceStatus $status
 * @property string|null $check_in
 * @property string|null $check_out
 * @property string|null $remark
 */
#[Fillable([
    'school_id',
    'sheet_id',
    'employee_id',
    'date',
    'status',
    'check_in',
    'check_out',
    'remark',
])]
class StaffAttendance extends Model
{
    /**
     * @return BelongsTo<StaffAttendanceSheet, $this>
     */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(StaffAttendanceSheet::class, 'sheet_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
