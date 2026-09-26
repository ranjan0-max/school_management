<?php

namespace App\Enums;

/**
 * A submitted sheet can still be edited; an approved one is locked until it is reopened.
 */
enum AttendanceSheetStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';

    public function label(): string
    {
        return $this === self::Submitted ? 'Awaiting approval' : 'Approved';
    }
}
