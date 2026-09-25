<?php

namespace App\Enums;

enum GuardianRelation: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Other = 'other';
}
