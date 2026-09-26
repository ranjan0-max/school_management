<?php

namespace App\Support\Attendance;

use App\Enums\AttendanceStatus;

/**
 * One person's row in a monthly register.
 */
final class PersonMonth
{
    /** @var array<int, AttendanceStatus> day of month => status */
    public array $days = [];

    public function add(int $day, AttendanceStatus $status): void
    {
        $this->days[$day] = $status;
    }

    public function on(int $day): ?AttendanceStatus
    {
        return $this->days[$day] ?? null;
    }

    public function count(AttendanceStatus $status): int
    {
        return count(array_filter($this->days, fn (AttendanceStatus $marked): bool => $marked === $status));
    }

    public function marked(): int
    {
        return count($this->days);
    }

    /**
     * Null when nothing is marked yet.
     */
    public function percentage(): ?float
    {
        if ($this->days === []) {
            return null;
        }

        $attended = array_sum(array_map(fn (AttendanceStatus $status): float => $status->attendedWeight(), $this->days));

        return round($attended / count($this->days) * 100, 1);
    }
}
