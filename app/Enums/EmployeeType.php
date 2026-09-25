<?php

namespace App\Enums;

enum EmployeeType: string
{
    case Teacher = 'teacher';
    case Staff = 'staff';

    /** Menu key that controls access to this list. */
    public function menuKey(): string
    {
        return match ($this) {
            self::Teacher => 'teachers',
            self::Staff => 'staff',
        };
    }

    /** Route name prefix, e.g. school.teachers.index. */
    public function routePrefix(): string
    {
        return 'school.'.$this->menuKey();
    }

    public function label(): string
    {
        return $this === self::Teacher ? 'Teacher' : 'Staff member';
    }

    public function pluralLabel(): string
    {
        return $this === self::Teacher ? 'Teachers' : 'Staff';
    }

    public function numberPrefix(): string
    {
        return $this === self::Teacher ? 'TCH' : 'STF';
    }
}
