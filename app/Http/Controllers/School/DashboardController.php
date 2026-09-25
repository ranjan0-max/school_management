<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $tenantContext): View
    {
        return view('school.dashboard', [
            'school' => $tenantContext->requireSchool(),
        ]);
    }
}
