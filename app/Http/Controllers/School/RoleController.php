<?php

namespace App\Http\Controllers\School;

use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\RoleRequest;
use App\Models\Role;
use App\Support\Access\AccessMatrix;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The school's own roles. Platform roles are not listed, cannot be given here, and their
 * names cannot be reused. Roles are deactivated, never deleted, and nobody can change the
 * role they hold themselves.
 */
class RoleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccessMatrix $access,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:roles', only: ['index']),
            new Middleware('menu:roles,create', only: ['create', 'store']),
            new Middleware('menu:roles,edit', only: ['edit', 'update']),
        ];
    }

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $state = in_array($request->string('state')->toString(), ['active', 'inactive'], true) ? $request->string('state')->toString() : '';

        $roles = Role::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->where('type', RoleType::School)
            ->withCount(['users', 'menus'])
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when($state !== '', fn (Builder $query) => $query->where('is_active', $state === 'active'))
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        return view('school.admin.roles.index', [
            'school' => $this->currentSchool(),
            'roles' => $roles,
            'search' => $search,
            'selectedState' => $state,
            'ownRoleId' => $request->user()?->role_id,
        ]);
    }

    public function create(): View
    {
        return view('school.admin.roles.form', [
            'school' => $this->currentSchool(),
            'role' => new Role(['type' => RoleType::School, 'is_active' => true]),
            'groups' => $this->access->catalog($this->currentSchool()),
            'grants' => [],
            'isOwnRole' => false,
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $school = $this->currentSchool();

        $role = DB::transaction(function () use ($attributes, $school, $request): Role {
            $role = Role::query()->create([
                'type' => RoleType::School,
                'school_id' => $school->getKey(),
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'all_school_menus' => $attributes['all_school_menus'],
                'is_active' => $attributes['is_active'],
            ]);

            $this->access->syncRoleMenus($role, $school, $request->input('menus', []));

            return $role;
        });

        $this->record('role.created', $request, $role);

        return redirect()->route('school.roles.edit', $role)->with('status', "Role {$role->name} created.");
    }

    public function edit(Request $request, Role $role): View
    {
        $this->ensureSchoolRole($role);

        return view('school.admin.roles.form', [
            'school' => $this->currentSchool(),
            'role' => $role,
            'groups' => $this->access->catalog($this->currentSchool()),
            'grants' => $this->access->roleGrants($role),
            'isOwnRole' => (int) $request->user()?->role_id === (int) $role->getKey(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureSchoolRole($role);
        abort_if((int) $request->user()?->role_id === (int) $role->getKey(), 403, 'You cannot change the role you hold.');

        $before = $this->auditValues($role);
        $attributes = $request->validated();

        DB::transaction(function () use ($role, $attributes, $request): void {
            $role->update([
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'all_school_menus' => $attributes['all_school_menus'],
                'is_active' => $attributes['is_active'],
            ]);

            $this->access->syncRoleMenus($role, $this->currentSchool(), $request->input('menus', []));
        });

        $this->record('role.updated', $request, $role->refresh(), $before);

        return back()->with('status', 'Role saved. It applies to everyone holding it.');
    }

    /**
     * Only this school's own roles are reachable here; platform roles look missing.
     */
    private function ensureSchoolRole(Role $role): void
    {
        abort_if($role->isPlatform(), 404);
        $this->ensureCurrentSchool($role);
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    private function record(string $event, Request $request, Role $role, ?array $before = null): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Role::class,
            auditableId: $role->getKey(),
            oldValues: $before,
            newValues: $this->auditValues($role),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Role $role): array
    {
        return [
            'name' => $role->name,
            'all_school_menus' => $role->all_school_menus,
            'is_active' => $role->is_active,
            'menu_ids' => $role->menus()->pluck('menus.id')->all(),
        ];
    }
}
