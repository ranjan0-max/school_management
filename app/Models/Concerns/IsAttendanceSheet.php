<?php

namespace App\Models\Concerns;

use App\Enums\AttendanceSheetStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared by student and staff attendance sheets: who took it, who approved it, and the lock.
 *
 * @property AttendanceSheetStatus $status
 * @property int|null $taken_by
 * @property int|null $approved_by
 */
trait IsAttendanceSheet
{
    public function isApproved(): bool
    {
        return $this->status === AttendanceSheetStatus::Approved;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceSheetStatus::class,
            'approved_at' => 'datetime',
        ];
    }
}
