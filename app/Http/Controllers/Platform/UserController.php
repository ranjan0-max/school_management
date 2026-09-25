<?php

namespace App\Http\Controllers\Platform;

use App\Enums\MenuAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreUserRequest;
use App\Http\Requests\Platform\UpdateUserRequest;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $schoolId = $request->integer('school_id') ?: null;
        $status = UserStatus::tryFrom($request->string('status')->toString());

        $users = User::query()
            ->where('is_super_admin', false)
            ->with(['school:id,name', 'role:id,name,type'])
            ->when($schoolId !== null, fn (Builder $query) => $query->where('school_id', $schoolId))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('platform.users.index', [
            'users' => $users,
            'schools' => School::query()->orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'selectedSchoolId' => $schoolId,
            'selectedStatus' => $status,
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $school = $request->integer('school_id') > 0 ? School::query()->find($request->integer('school_id')) : null;

        return view('platform.users.create', [
            'user' => new User(['school_id' => $school?->getKey(), 'status' => UserStatus::Active]),
            'schools' => School::query()->orderBy('name')->get(['id', 'name']),
            'roles' => $this->assignableRoles((int) ($request->old('school_id') ?? $school?->getKey())),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $attributes = $request->validated();

        $user = DB::transaction(function () use ($attributes): User {
            return User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
                'password' => $attributes['password'],
                'school_id' => $attributes['school_id'],
                'role_id' => $attributes['role_id'],
                'status' => $attributes['status'],
                'is_super_admin' => false,
            ]);
        });

        $this->audit->record(
            event: 'user.created',
            actor: $request->user(),
            school: $user->school,
            auditableType: User::class,
            auditableId: $user->getKey(),
            newValues: $this->auditValues($user),
        );

        return redirect()
            ->route('platform.users.edit', $user)
            ->with('status', 'User created. You can now add individual menu access if needed.');
    }

    public function edit(Request $request, User $user): View
    {
        abort_if($user->isSuperAdmin(), 404);

        $user->load(['school', 'role']);

        return view('platform.users.edit', [
            'user' => $user,
            'schools' => School::query()->orderBy('name')->get(['id', 'name']),
            'roles' => $this->assignableRoles((int) ($request->old('school_id') ?? $user->school_id)),
            'statuses' => UserStatus::cases(),
            'groups' => $this->schoolCatalog($user->school),
            'roleGrants' => $this->roleGrants($user->role, $user->school),
            'overrides' => $this->currentOverrides($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 404);

        $oldValues = $this->auditValues($user);
        $attributes = $request->validated();

        DB::transaction(function () use ($user, $attributes, $request): void {
            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
                'school_id' => $attributes['school_id'],
                'role_id' => $attributes['role_id'],
                'status' => $attributes['status'],
            ]);

            if (! empty($attributes['password'])) {
                $user->password = $attributes['password'];
            }

            $user->save();

            $this->syncOverrides($user, $request->input('overrides', []));
        });

        $this->audit->record(
            event: 'user.updated',
            actor: $request->user(),
            school: $user->school,
            auditableType: User::class,
            auditableId: $user->getKey(),
            oldValues: $oldValues,
            newValues: $this->auditValues($user->refresh()),
            metadata: ['password_changed' => ! empty($attributes['password'])],
        );

        return back()->with('status', 'User updated successfully.');
    }

    /**
     * Platform roles plus the selected school's roles only; other schools' roles are
     * fetched from platform.roles.options when the school changes on the form.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(int $schoolId): Collection
    {
        return Role::query()
            ->assignableIn($schoolId)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'type', 'school_id', 'name']);
    }

    /**
     * @return Collection<int, Menu>
     */
    private function schoolCatalog(?School $school): Collection
    {
        if ($school === null) {
            return new Collection;
        }

        return Menu::catalog($school->menus()->pluck('menus.id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * What the role alone grants, so the screen can show "Role: allowed / not allowed".
     *
     * @return array<int, array<string, bool>>
     */
    private function roleGrants(?Role $role, ?School $school): array
    {
        if ($role === null || ! $role->is_active || ! $role->isUsableIn($school?->getKey())) {
            return [];
        }

        $grants = [];

        foreach ($this->schoolCatalog($school)->flatMap->children as $menu) {
            foreach ($menu->availableActions() as $action) {
                $grants[$menu->getKey()][$action->value] = $role->all_school_menus;
            }
        }

        if (! $role->all_school_menus) {
            foreach (DB::table('role_menus')->where('role_id', $role->getKey())->get() as $row) {
                foreach (MenuAction::cases() as $action) {
                    if (isset($grants[(int) $row->menu_id][$action->value])) {
                        $grants[(int) $row->menu_id][$action->value] = (bool) $row->{$action->column()};
                    }
                }
            }
        }

        return $grants;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function currentOverrides(User $user): array
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
     * Only pages assigned to the user's current school, and only actions each page supports.
     */
    private function syncOverrides(User $user, mixed $input): void
    {
        $input = is_array($input) ? $input : [];
        $rows = [];

        foreach ($this->schoolCatalog($user->school()->first())->flatMap->children as $menu) {
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
     * @return array<string, mixed>
     */
    private function auditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'school_id' => $user->school_id,
            'role_id' => $user->role_id,
            'status' => $user->status->value,
            'override_menu_ids' => $user->menuOverrides()->pluck('menus.id')->all(),
        ];
    }
}
