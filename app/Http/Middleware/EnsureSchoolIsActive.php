<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolIsActive
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        abort_unless(
            $this->tenantContext->requireSchool()->isOperational(),
            403,
            'This school is not currently active.',
        );

        return $next($request);
    }
}
