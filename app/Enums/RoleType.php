<?php

namespace App\Enums;

enum RoleType: string
{
    /** Created and assigned only by Super Admin; reusable in every school. */
    case Platform = 'platform';

    /** Belongs to one school and is usable only inside it. */
    case School = 'school';
}
