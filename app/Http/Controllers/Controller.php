<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    /**
     * The school resolved by the tenant.resolve middleware.
     */
    protected function currentSchool(): School
    {
        return app(TenantContext::class)->requireSchool();
    }

    /**
     * Route-bound records must belong to the current school; anything else is treated as missing.
     */
    protected function ensureCurrentSchool(Model $record): void
    {
        abort_unless((int) $record->getAttribute('school_id') === (int) $this->currentSchool()->getKey(), 404);
    }
}
