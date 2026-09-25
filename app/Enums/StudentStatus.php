<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Left = 'left';
    case PassedOut = 'passed_out';

    public function label(): string
    {
        return $this === self::PassedOut ? 'Passed out' : ucfirst($this->value);
    }
}
