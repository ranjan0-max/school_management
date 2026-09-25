<?php

namespace App\Support\Access;

use App\Enums\MenuAction;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place that decides what a user may do. Order of checks:
 *
 * 1. Super Admin → everything.
 * 2. The page must be assigned to the user's school (school_menus) and support the action.
 * 3. A user override (user_menu_overrides) of true/false wins over the role.
 * 4. Otherwise the role decides: "all school menus" grants every assigned page,
 *    else role_menus must grant the action. Inactive roles, or a school role that
 *    belongs to another school, grant nothing.
 */
class MenuAccess
{
    /** @var array<int, array<string, array<string, bool>>> */
    private array $resolved = [];

    public function allows(User $user, string $menuKey, MenuAction $action = MenuAction::View): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->grantsFor($user)[$menuKey][$action->value] ?? false;
    }

    /**
     * Menu keys the user can at least view.
     *
     * @return array<int, string>
     */
    public function visibleMenuKeys(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Menu::query()->pages()->pluck('key')->all();
        }

        return array_keys(array_filter(
            $this->grantsFor($user),
            fn (array $actions): bool => $actions[MenuAction::View->value] ?? false,
        ));
    }

    /**
     * Effective grants for a school user, resolved once per request.
     *
     * @return array<string, array<string, bool>>
     */
    public function grantsFor(User $user): array
    {
        $userId = (int) $user->getKey();

        return $this->resolved[$userId] ??= $this->resolve($user);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function resolve(User $user): array
    {
        $schoolId = $user->school_id === null ? null : (int) $user->school_id;

        if ($schoolId === null) {
            return [];
        }

        $menus = DB::table('menus')
            ->join('school_menus', 'school_menus.menu_id', '=', 'menus.id')
            ->where('school_menus.school_id', $schoolId)
            ->whereNotNull('menus.parent_id')
            ->where('menus.is_active', true)
            ->get(['menus.id', 'menus.key', 'menus.actions']);

        if ($menus->isEmpty()) {
            return [];
        }

        $role = $user->role;
        $roleIsUsable = $role !== null && $role->is_active && $role->isUsableIn($schoolId);
        $grantsAll = $roleIsUsable && $role->all_school_menus;

        $roleRows = $roleIsUsable && ! $grantsAll
            ? DB::table('role_menus')->where('role_id', $role->getKey())->get()->keyBy('menu_id')
            : collect();

        $overrides = DB::table('user_menu_overrides')->where('user_id', $user->getKey())->get()->keyBy('menu_id');

        $grants = [];

        foreach ($menus as $menu) {
            $roleRow = $roleRows->get($menu->id);
            $override = $overrides->get($menu->id);

            foreach (Menu::parseActions($menu->actions) as $action) {
                $column = $action->column();
                $overrideValue = $override?->{$column};

                $grants[$menu->key][$action->value] = $overrideValue !== null
                    ? (bool) $overrideValue
                    : ($grantsAll || (bool) ($roleRow->{$column} ?? false));
            }
        }

        return $grants;
    }
}
