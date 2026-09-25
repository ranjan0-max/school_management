<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('platform.dashboard');
        }

        return redirect()->route('school.dashboard');
    }
}
