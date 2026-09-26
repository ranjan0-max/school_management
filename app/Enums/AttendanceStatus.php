<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Leave = 'leave';

    public function label(): string
    {
        return $this === self::HalfDay ? 'Half day' : ucfirst($this->value);
    }

    /**
     * One or two letters for register grids.
     */
    public function code(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Absent => 'A',
            self::Late => 'L',
            self::HalfDay => 'HD',
            self::Leave => 'LV',
        };
    }

    /**
     * How much of the day counts as attended: late counts in full, half day as half.
     */
    public function attendedWeight(): float
    {
        return match ($this) {
            self::Present, self::Late => 1.0,
            self::HalfDay => 0.5,
            self::Absent, self::Leave => 0.0,
        };
    }
}
