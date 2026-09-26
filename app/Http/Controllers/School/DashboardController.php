<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext): View
    {
        $school = $tenantContext->requireSchool();
        $user = $request->user();

        // The latest notices for this user, pinned first, when they may see notices at all.
        $notices = $user?->can('menu', ['notices', 'view'])
            ? Notice::query()
                ->where('school_id', $school->getKey())
                ->visibleTo($user, CarbonImmutable::now($school->timezone ?: config('app.timezone'))->toDateString())
                ->withExists(['reads as read_by_me' => fn ($query) => $query->where('user_id', $user->getKey())])
                ->boardOrder()
                ->limit(5)
                ->get(['id', 'title', 'is_pinned', 'publish_at', 'created_at'])
            : null;

        return view('school.dashboard', [
            'school' => $school,
            'notices' => $notices,
        ]);
    }
}
