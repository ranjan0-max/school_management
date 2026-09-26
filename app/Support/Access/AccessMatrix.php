<?php

namespace App\Support\Access;

use App\Enums\MenuAction;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes the permission tables behind the role and user access screens.
 * Used by Super Admin (platform) and by school administrators, so the same rules apply:
 * only real pages, only actions a page supports, and a school only sees its own pages.
 */
class AccessMatrix
{
    /**
     * Groups with their pages: every page when $school is null, otherwise the school's pages.
     *
     * @return Collection<int, Menu>
     */
    public function catalog(?School $school): Collection
    {
        return Menu::catalog($school?->menus()->pluck('menus.id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * The role's ticked actions: menu id => action => bool.
     *
     * @return array<int, array<string, bool>>
     */
    public function roleGrants(Role $role): array
    {
        $grants = [];

        foreach (DB::table('role_menus')->where('role_id', $role->getKey())->get() as $row) {
            foreach (MenuAction::cases() as $action) {
                $grants[(int) $row->menu_id][$action->value] = (bool) $row->{$action->column()};
            }
        }

        return $grants;
    }

    /**
     * What a role really gives inside a school ("all school menus" included), so the user
     * screen can show "Role: allowed / not allowed" next to each override.
     *
     * @return array<int, array<string, bool>>
     */
    public function effectiveRoleGrants(?Role $role, ?School $school): array
    {
        if ($role === null || ! $role->is_active || ! $role->isUsableIn($school?->getKey())) {
            return [];
        }

        $grants = [];

        foreach ($this->schoolPages($school) as $menu) {
            foreach ($menu->availableActions() as $action) {
                $grants[$menu->getKey()][$action->value] = $role->all_school_menus;
            }
        }

        if (! $role->all_school_menus) {
            foreach ($this->roleGrants($role) as $menuId => $actions) {
                foreach ($actions as $action => $allowed) {
                    if (isset($grants[$menuId][$action])) {
                        $grants[$menuId][$action] = $allowed;
                    }
                }
            }
        }

        return $grants;
    }

    /**
     * The user's own overrides: menu id => action => 'allow' | 'deny'.
     *
     * @return array<int, array<string, string>>
     */
    public function userOverrides(User $user): array
    {
        $overrides = [];

        foreach (DB::table('user_menu_overrides')->where('user_id', $user->getKey())->get() as $row) {
            foreach (MenuAction::cases() as $action) {
                $value = $row->{$action->column()};

                if ($value !== null) {
                    $overrides[(int) $row->menu_id][$action->value] = $value ? 'allow' : 'deny';
                }
            }
        }

        return $overrides;
    }

    /**
     * Saves the role's ticks. Pages outside the catalog and unsupported actions are ignored,
     * so tampered form input can never grant something outside the school.
     */
    public function syncRoleMenus(Role $role, ?School $school, mixed $input): void
    {
        $input = is_array($input) ? $input : [];
        $rows = [];

        foreach ($this->catalog($school)->flatMap->children as $menu) {
            $requested = $input[$menu->getKey()] ?? [];

            if (! is_array($requested)) {
                continue;
            }

            $row = array_fill_keys(Menu::ACTION_COLUMNS, false);

            foreach ($menu->availableActions() as $action) {
                $row[$action->column()] = filter_var($requested[$action->value] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            if (in_array(true, $row, true)) {
                $rows[$menu->getKey()] = $row;
            }
        }

        $role->menus()->sync($rows);
    }

    /**
     * Saves allow / deny overrides, only for pages of the user's current school.
     */
    public function syncUserOverrides(User $user, mixed $input): void
    {
        $input = is_array($input) ? $input : [];
        $rows = [];

        foreach ($this->schoolPages($user->school()->first()) as $menu) {
            $requested = $input[$menu->getKey()] ?? [];

            if (! is_array($requested)) {
                continue;
            }

            $row = array_fill_keys(Menu::ACTION_COLUMNS, null);

            foreach ($menu->availableActions() as $action) {
                $row[$action->column()] = match ($requested[$action->value] ?? null) {
                    'allow' => true,
                    'deny' => false,
                    default => null,
                };
            }

            if (array_filter($row, fn (?bool $value): bool => $value !== null) !== []) {
                $rows[$menu->getKey()] = $row;
            }
        }

        $user->menuOverrides()->sync($rows);
    }

    /**
     * A user's pages come from their school; without a school there are none.
     *
     * @return \Illuminate\Support\Collection<int, Menu>
     */
    private function schoolPages(?School $school): \Illuminate\Support\Collection
    {
        return $school === null ? collect() : $this->catalog($school)->flatMap->children;
    }
}
