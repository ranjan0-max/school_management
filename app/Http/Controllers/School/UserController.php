<?php

namespace App\Http\Controllers\School;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessMatrix;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The school's own users, managed by the school administrator.
 *
 * - Users are never deleted, only made inactive (an inactive user cannot sign in).
 * - Only the school's roles can be given; platform roles stay with the Super Admin,
 *   and a user holding one is read-only here.
 * - Nobody can change their own role, status or access from this screen.
 */
class UserController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccessMatrix $access,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:users', only: ['index']),
            new Middleware('menu:users,create', only: ['create', 'store']),
            new Middleware('menu:users,edit', only: ['edit', 'update', 'status']),
        ];
    }

    public function index(Request $request): View
    {
        $schoolId = $this->currentSchool()->getKey();
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $status = UserStatus::tryFrom($request->string('status')->toString());
        $roles = $this->schoolRoles();
        $roleId = $roles->contains('id', $request->integer('role_id')) ? $request->integer('role_id') : null;

        $users = User::query()
            ->where('school_id', $schoolId)
            ->where('is_super_admin', false)
            ->with('role:id,name,type,is_active')
            ->when($roleId !== null, fn (Builder $query) => $query->where('role_id', $roleId))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('school.admin.users.index', [
            'school' => $this->currentSchool(),
            'users' => $users,
            'roles' => $roles,
            'search' => $search,
            'selectedRoleId' => $roleId,
            'selectedStatus' => $status,
            'statuses' => [UserStatus::Active, UserStatus::Inactive],
        ]);
    }

    public function create(): View
    {
        return view('school.admin.users.form', [
            'school' => $this->currentSchool(),
            'user' => new User(['status' => UserStatus::Active]),
            'roles' => $this->schoolRoles(activeOnly: true),
            'lock' => null,
            'groups' => new Collection,
            'roleGrants' => [],
            'overrides' => [],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $attributes = $request->validated();

        $user = User::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'password' => $attributes['password'],
            'school_id' => $this->currentSchool()->getKey(),
            'role_id' => $attributes['role_id'],
            'status' => $attributes['status'],
            'is_super_admin' => false,
        ]);

        $this->record('user.created', $request, $user);

        return redirect()
            ->route('school.users.edit', $user)
            ->with('status', "User {$user->name} created. You can give extra access below if needed.");
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureSchoolUser($user);
        $user->load('role');

        return view('school.admin.users.form', [
            'school' => $this->currentSchool(),
            'user' => $user,
            'roles' => $this->schoolRoles(activeOnly: true, keepRoleId: $user->role_id),
            'lock' => $this->lockReason($request->user(), $user),
            'groups' => $this->access->catalog($this->currentSchool()),
            'roleGrants' => $this->access->effectiveRoleGrants($user->role, $this->currentSchool()),
            'overrides' => $this->access->userOverrides($user),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->ensureSchoolUser($user);
        $lock = $this->lockReason($request->user(), $user);
        abort_if($lock === 'platform_role', 403);

        $before = $this->auditValues($user);
        $attributes = $request->validated();

        DB::transaction(function () use ($user, $attributes, $lock, $request): void {
            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
            ]);

            if (! empty($attributes['password'])) {
                $user->password = $attributes['password'];
            }

            // Your own role, status and extra access can only be changed by someone else.
            if ($lock !== 'self') {
                $user->fill(['role_id' => $attributes['role_id'], 'status' => $attributes['status']]);
                $this->access->syncUserOverrides($user, $request->input('overrides', []));
            }

            $user->save();
        });

        $this->record('user.updated', $request, $user->refresh(), $before, ['password_changed' => ! empty($attributes['password'])]);

        return back()->with('status', 'User saved.');
    }

    /**
     * Activate or deactivate from the list. Users are never deleted.
     */
    public function status(Request $request, User $user): RedirectResponse
    {
        $this->ensureSchoolUser($user);
        abort_if($this->lockReason($request->user(), $user) !== null, 403);

        $before = $this->auditValues($user);
        $user->update(['status' => $user->status === UserStatus::Active ? UserStatus::Inactive : UserStatus::Active]);
        $this->record($user->status === UserStatus::Active ? 'user.activated' : 'user.deactivated', $request, $user, $before);

        return back()->with('status', $user->status === UserStatus::Active
            ? "{$user->name} can sign in again."
            : "{$user->name} is now inactive and cannot sign in.");
    }

    private function ensureSchoolUser(User $user): void
    {
        $this->ensureCurrentSchool($user);
        abort_if($user->isSuperAdmin(), 404);
    }

    /**
     * Why access fields are locked: "self" (your own account) or "platform_role"
     * (given by the Super Admin). Null when the user can be fully edited.
     */
    private function lockReason(?User $actor, User $user): ?string
    {
        if ($actor !== null && $actor->is($user)) {
            return 'self';
        }

        return $user->role?->isPlatform() ? 'platform_role' : null;
    }

    /**
     * @return Collection<int, Role>
     */
    private function schoolRoles(bool $activeOnly = false, ?int $keepRoleId = null): Collection
    {
        return Role::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->where('type', RoleType::School)
            ->when($activeOnly, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when($keepRoleId !== null, fn (Builder $query) => $query->orWhere('id', $keepRoleId))))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $metadata
     */
    private function record(string $event, Request $request, User $user, ?array $before = null, ?array $metadata = null): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: User::class,
            auditableId: $user->getKey(),
            oldValues: $before,
            newValues: $this->auditValues($user),
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'status' => $user->status->value,
            'override_menu_ids' => $user->menuOverrides()->pluck('menus.id')->all(),
        ];
    }
}
