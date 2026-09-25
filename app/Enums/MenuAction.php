<?php

namespace App\Enums;

enum MenuAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';
    case Export = 'export';

    /** Sensitive actions such as fee refunds, result publishing, or attendance locking. */
    case Approve = 'approve';

    public function column(): string
    {
        return 'can_'.$this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
