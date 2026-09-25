<?php

namespace App\Http\Controllers\School;

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Enums\Gender;
use App\Enums\MenuAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\EmployeeRequest;
use App\Models\Employee;
use App\Support\Audit\AuditLogger;
use App\Support\Numbering\SchoolNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Serves both /school/teachers and /school/staff. The type comes from the route,
 * and each type is protected by its own menu (teachers / staff).
 * Employees are never deleted; mark them as "left" so history stays intact.
 */
class EmployeeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $type = $this->type($request, MenuAction::View);
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $status = EmployeeStatus::tryFrom($request->string('status')->toString());

        $employees = Employee::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->where('type', $type)
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_no', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('school.employees.index', [
            'school' => $this->currentSchool(),
            'type' => $type,
            'employees' => $employees,
            'search' => $search,
            'selectedStatus' => $status,
            'statuses' => EmployeeStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $type = $this->type($request, MenuAction::Create);

        return view('school.employees.form', [
            'school' => $this->currentSchool(),
            'type' => $type,
            'employee' => new Employee(['type' => $type, 'status' => EmployeeStatus::Active]),
            'genders' => Gender::cases(),
            'statuses' => EmployeeStatus::cases(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $type = $this->type($request, MenuAction::Create);
        $school = $this->currentSchool();

        $employee = DB::transaction(function () use ($request, $type, $school): Employee {
            $attributes = $request->validated();
            $attributes['employee_no'] ??= SchoolNumber::next($school, 'employees', 'employee_no', $type->numberPrefix().'-');

            return Employee::query()->create([
                ...$attributes,
                'school_id' => $school->getKey(),
                'type' => $type,
            ]);
        });

        $this->record('employee.created', $request, $employee);

        return redirect()->route($type->routePrefix().'.index')
            ->with('status', "{$type->label()} {$employee->name} added ({$employee->employee_no}).");
    }

    public function edit(Request $request, Employee $employee): View
    {
        $type = $this->type($request, MenuAction::Edit);
        $this->ensureEmployeeOfType($employee, $type);

        return view('school.employees.form', [
            'school' => $this->currentSchool(),
            'type' => $type,
            'employee' => $employee,
            'genders' => Gender::cases(),
            'statuses' => EmployeeStatus::cases(),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $type = $this->type($request, MenuAction::Edit);
        $this->ensureEmployeeOfType($employee, $type);

        $attributes = $request->validated();
        $attributes['employee_no'] ??= $employee->employee_no;

        $employee->update($attributes);
        $this->record('employee.updated', $request, $employee);

        return redirect()->route($type->routePrefix().'.index')->with('status', "{$employee->name} updated.");
    }

    /**
     * Reads teacher/staff from the route name and checks that menu's permission.
     */
    private function type(Request $request, MenuAction $action): EmployeeType
    {
        $type = str_starts_with((string) $request->route()?->getName(), 'school.teachers.')
            ? EmployeeType::Teacher
            : EmployeeType::Staff;

        Gate::authorize('menu', [$type->menuKey(), $action->value]);

        return $type;
    }

    /**
     * A staff record must not be reachable through the teachers URL (and vice versa).
     */
    private function ensureEmployeeOfType(Employee $employee, EmployeeType $type): void
    {
        $this->ensureCurrentSchool($employee);
        abort_unless($employee->type === $type, 404);
    }

    private function record(string $event, Request $request, Employee $employee): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Employee::class,
            auditableId: $employee->getKey(),
            newValues: [
                'employee_no' => $employee->employee_no,
                'type' => $employee->type->value,
                'name' => $employee->name,
                'status' => $employee->status->value,
            ],
        );
    }
}
