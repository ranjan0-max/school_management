<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SchoolStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSchoolRequest;
use App\Http\Requests\Platform\UpdateSchoolAccessRequest;
use App\Http\Requests\Platform\UpdateSchoolRequest;
use App\Models\Menu;
use App\Models\School;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $status = $request->string('status')->toString();
        $validStatuses = array_column(SchoolStatus::cases(), 'value');

        if (! in_array($status, $validStatuses, true)) {
            $status = '';
        }

        $schools = School::query()
            ->select(['id', 'name', 'slug', 'code', 'email', 'status', 'trial_ends_at', 'created_at'])
            ->withCount('users')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('platform.schools.index', [
            'schools' => $schools,
            'search' => $search,
            'selectedStatus' => $status,
            'statuses' => SchoolStatus::cases(),
            'schoolCount' => School::query()->count(),
            'operationalCount' => School::query()
                ->whereIn('status', [SchoolStatus::Active->value, SchoolStatus::Trial->value])
                ->count(),
        ]);
    }

    public function create(): View
    {
        return view('platform.schools.create', [
            'school' => new School([
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
                'currency' => 'INR',
                'status' => SchoolStatus::Trial,
            ]),
            'statuses' => SchoolStatus::cases(),
        ]);
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['slug'] = $this->uniqueSlug($attributes['slug'] ?? null, $attributes['name']);

        $school = DB::transaction(fn () => School::query()->create($attributes));

        Log::notice('School created by Super Admin.', [
            'actor_id' => $request->user()?->getKey(),
            'school_id' => $school->getKey(),
            'ip_address' => $request->ip(),
        ]);

        $this->audit->record(
            event: 'school.created',
            actor: $request->user(),
            school: $school,
            auditableType: School::class,
            auditableId: $school->getKey(),
            newValues: [
                'name' => $school->name,
                'code' => $school->code,
                'status' => $request->string('status')->toString(),
            ],
        );

        return redirect()
            ->route('platform.schools.edit', $school)
            ->with('status', 'School created successfully. You can now review its configuration.');
    }

    public function edit(School $school): View
    {
        return view('platform.schools.edit', [
            'school' => $school,
            'statuses' => SchoolStatus::cases(),
        ]);
    }

    public function update(UpdateSchoolRequest $request, School $school): RedirectResponse
    {
        $oldValues = [
            'name' => $school->name,
            'code' => $school->code,
            'status' => $school->getRawOriginal('status'),
        ];
        $attributes = $request->validated();
        $attributes['slug'] = $this->uniqueSlug($attributes['slug'] ?? null, $attributes['name'], $school);

        DB::transaction(fn () => $school->update($attributes));

        Log::notice('School updated by Super Admin.', [
            'actor_id' => $request->user()?->getKey(),
            'school_id' => $school->getKey(),
            'status' => $request->string('status')->toString(),
            'ip_address' => $request->ip(),
        ]);

        $this->audit->record(
            event: 'school.updated',
            actor: $request->user(),
            school: $school,
            auditableType: School::class,
            auditableId: $school->getKey(),
            oldValues: $oldValues,
            newValues: [
                'name' => $school->name,
                'code' => $school->code,
                'status' => $request->string('status')->toString(),
            ],
        );

        return back()->with('status', 'School details updated successfully.');
    }

    public function access(School $school): View
    {
        return view('platform.schools.access', [
            'school' => $school,
            'groups' => Menu::catalog(),
            'assignedMenuIds' => $school->menus()->pluck('menus.id')->map(fn (mixed $id): int => (int) $id)->all(),
        ]);
    }

    public function updateAccess(UpdateSchoolAccessRequest $request, School $school): RedirectResponse
    {
        $previousMenuIds = $school->menus()->pluck('menus.id')->map(fn (mixed $id): int => (int) $id)->all();
        $menuIds = array_values(array_unique(array_map('intval', $request->validated('menu_ids', []))));

        $school->menus()->sync($menuIds);

        Log::notice('School menu access updated.', [
            'actor_id' => $request->user()?->getKey(),
            'school_id' => $school->getKey(),
            'menu_ids' => $menuIds,
            'ip_address' => $request->ip(),
        ]);

        $this->audit->record(
            event: 'school.access.updated',
            actor: $request->user(),
            school: $school,
            auditableType: School::class,
            auditableId: $school->getKey(),
            oldValues: ['menu_ids' => $previousMenuIds],
            newValues: ['menu_ids' => $menuIds],
        );

        return back()->with('status', 'School menus updated successfully.');
    }

    /**
     * Super Admin opens a school's workspace.
     */
    public function enter(Request $request, School $school): RedirectResponse
    {
        $request->session()->put('active_school_id', $school->getKey());

        $this->audit->record(
            event: 'school.context_entered',
            actor: $request->user(),
            school: $school,
            auditableType: School::class,
            auditableId: $school->getKey(),
        );

        return redirect()->route('school.dashboard');
    }

    /**
     * Super Admin returns from a school's workspace to Platform Control.
     */
    public function leave(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $previousSchoolId = $request->session()->pull('active_school_id');
        $tenantContext->clear();

        $this->audit->record(
            event: 'school.context_exited',
            actor: $request->user(),
            school: is_numeric($previousSchoolId) ? School::query()->find((int) $previousSchoolId) : null,
            auditableType: School::class,
            auditableId: is_numeric($previousSchoolId) ? (int) $previousSchoolId : null,
        );

        return redirect()->route('platform.dashboard');
    }

    private function uniqueSlug(?string $requestedSlug, string $name, ?School $school = null): string
    {
        $base = Str::slug($requestedSlug ?: $name) ?: 'school';
        $slug = $base;
        $suffix = 2;

        while (School::query()
            ->where('slug', $slug)
            ->when($school !== null, fn (Builder $query) => $query->whereKeyNot($school->getKey()))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
