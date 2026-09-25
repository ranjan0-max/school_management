<?php

namespace App\Http\Middleware;

use App\Enums\MenuAction;
use App\Support\Access\MenuAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('menu:students') or ->middleware('menu:fee_payments,approve').
 */
class RequireMenuAccess
{
    public function __construct(private readonly MenuAccess $access) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $menuKey, string $action = 'view'): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        abort_unless($this->access->allows($user, $menuKey, MenuAction::from($action)), 403);

        return $next($request);
    }
}
