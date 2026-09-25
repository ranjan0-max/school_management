<?php

namespace App\Http\Controllers\Platform;

use App\Enums\MenuAction;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreRoleRequest;
use App\Http\Requests\Platform\UpdateRoleRequest;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $type = RoleType::tryFrom($request->string('type')->toString());
        $schoolId = $request->integer('school_id') ?: null;

        $roles = Role::query()
            ->with('school:id,name')
            ->withCount(['users', 'menus'])
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->when($schoolId !== null, fn (Builder $query) => $query->where('school_id', $schoolId))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        return view('platform.roles.index', [
            'roles' => $roles,
            'schools' => School::query()->orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'selectedType' => $type,
            'selectedSchoolId' => $schoolId,
        ]);
    }

    /**
     * Roles a user of the given school may hold, for the user form's role dropdown.
     */
    public function options(Request $request): JsonResponse
    {
        $schoolId = $request->integer('school_id');

        return response()->json(
            $schoolId > 0
                ? Role::query()
                    ->assignableIn($schoolId)
                    ->where('type', RoleType::School)
                    ->orderBy('name')
                    ->limit(500)
                    ->get(['id', 'name'])
                : [],
        );
    }

    public function create(Request $request): View
    {
        $school = $request->string('type')->toString() === RoleType::School->value && $request->integer('school_id') > 0
            ? School::query()->whereKey($request->integer('school_id'))->firstOrFail()
            : null;
        $type = $request->string('type')->toString() === RoleType::School->value ? RoleType::School : RoleType::Platform;

        return view('platform.roles.create', [
            'role' => new Role(['type' => $type, 'school_id' => $school?->getKey(), 'is_active' => true]),
            'school' => $school,
            'schools' => School::query()->orderBy('name')->get(['id', 'name']),
            'groups' => $type === RoleType::School && $school === null ? new Collection : $this->menuCatalog($school),
            'grants' => [],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $type = RoleType::from($attributes['type']);
        $school = $type === RoleType::School ? School::query()->whereKey($attributes['school_id'])->firstOrFail() : null;

        $role = DB::transaction(function () use ($attributes, $type, $school, $request): Role {
            $role = Role::query()->create([
                'type' => $type,
                'school_id' => $school?->getKey(),
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'all_school_menus' => $attributes['all_school_menus'],
                'is_active' => $attributes['is_active'],
            ]);

            $this->syncMenus($role, $school, $request->input('menus', []));

            return $role;
        });

        $this->audit->record(
            event: 'role.created',
            actor: $request->user(),
            school: $school,
            auditableType: Role::class,
            auditableId: $role->getKey(),
            newValues: $this->auditValues($role),
        );

        return redirect()->route('platform.roles.edit', $role)->with('status', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        return view('platform.roles.edit', [
            'role' => $role,
            'school' => $role->school,
            'schools' => collect(),
            'groups' => $this->menuCatalog($role->school),
            'grants' => $this->currentGrants($role),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $oldValues = $this->auditValues($role);
        $attributes = $request->validated();

        DB::transaction(function () use ($role, $attributes, $request): void {
            $role->update([
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'all_school_menus' => $attributes['all_school_menus'],
                'is_active' => $attributes['is_active'],
            ]);

            $this->syncMenus($role, $role->school, $request->input('menus', []));
        });

        $this->audit->record(
            event: 'role.updated',
            actor: $request->user(),
            school: $role->school,
            auditableType: Role::class,
            auditableId: $role->getKey(),
            oldValues: $oldValues,
            newValues: $this->auditValues($role->refresh()),
        );

        return back()->with('status', $role->isPlatform()
            ? 'Role updated. Changes apply to every school using it.'
            : 'Role updated successfully.');
    }

    /**
     * Pages a role may receive: every page for platform roles, only the school's pages for school roles.
     *
     * @return Collection<int, Menu>
     */
    private function menuCatalog(?School $school): Collection
    {
        return Menu::catalog($school?->menus()->pluck('menus.id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * Keeps only real pages from the catalog and only actions each page supports,
     * so tampered form input can never grant something outside the school.
     */
    private function syncMenus(Role $role, ?School $school, mixed $input): void
    {
        $input = is_array($input) ? $input : [];
        $rows = [];

        foreach ($this->menuCatalog($school)->flatMap->children as $menu) {
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
     * @return array<int, array<string, bool>>
     */
    private function currentGrants(Role $role): array
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
     * @return array<string, mixed>
     */
    private function auditValues(Role $role): array
    {
        return [
            'type' => $role->type->value,
            'name' => $role->name,
            'all_school_menus' => $role->all_school_menus,
            'is_active' => $role->is_active,
            'menu_ids' => $role->menus()->pluck('menus.id')->all(),
        ];
    }
}
