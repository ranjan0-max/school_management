<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $school = $user->isSuperAdmin()
            ? $this->resolveForSuperAdmin($request)
            : $user->school;

        // Super Admin may open any school page; they only have to say which school's data to show.
        if ($school === null && $user->isSuperAdmin()) {
            return redirect()->guest(route('platform.schools.index'))
                ->with('status', 'Choose a school to open this page.');
        }

        abort_unless($school !== null, 403, 'No school is assigned to this account.');

        $this->tenantContext->setSchool($school);
        $request->attributes->set('school', $school);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }

    private function resolveForSuperAdmin(Request $request): ?School
    {
        $schoolId = $request->session()->get('active_school_id');

        if (! is_numeric($schoolId)) {
            return null;
        }

        $school = School::query()->find((int) $schoolId);

        if (! $school) {
            $request->session()->forget('active_school_id');
        }

        return $school;
    }
}
