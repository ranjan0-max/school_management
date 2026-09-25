<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('platform.dashboard', [
            'schools' => School::query()
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'code', 'status']),
            'schoolCount' => School::query()->count(),
            'userCount' => User::query()->where('is_super_admin', false)->count(),
            'roleCount' => Role::query()->count(),
        ]);
    }
}
