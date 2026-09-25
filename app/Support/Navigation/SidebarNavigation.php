<?php

namespace App\Support\Navigation;

use App\Models\User;
use App\Support\Access\MenuAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use stdClass;

class SidebarNavigation
{
    public function __construct(private readonly MenuAccess $access) {}

    /**
     * Visible pages that already have a route, grouped by their parent menu name.
     *
     * @return Collection<string, Collection<int, stdClass>>
     */
    public function for(User $user): Collection
    {
        // School pages only make sense inside a school: Super Admin sees them after entering one.
        if ($user->isSuperAdmin() && ! request()->session()->has('active_school_id')) {
            return collect();
        }

        $keys = $this->access->visibleMenuKeys($user);

        if ($keys === []) {
            return collect();
        }

        return DB::table('menus')
            ->join('menus as groups', 'groups.id', '=', 'menus.parent_id')
            ->whereIn('menus.key', $keys)
            ->where('groups.is_active', true)
            ->whereNotNull('menus.route_name')
            ->orderBy('groups.sort_order')
            ->orderBy('menus.sort_order')
            ->get(['menus.key', 'menus.name', 'menus.route_name', 'menus.icon', 'groups.name as group_name'])
            ->filter(fn (stdClass $menu): bool => Route::has((string) $menu->route_name))
            ->groupBy('group_name');
    }
}
