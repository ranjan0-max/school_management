<?php

namespace App\Http\Controllers\School;

use App\Enums\AttendanceSheetStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\School\Concerns\TakesAttendance;
use App\Http\Requests\School\StaffAttendanceRequest;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceSheet;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Daily attendance of teachers and staff. The list is paginated and filtered on the
 * server; saving a page updates only the people on that page.
 */
class StaffAttendanceController extends Controller implements HasMiddleware
{
    use TakesAttendance;

    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            // Saving checks create (new sheet) or edit (existing sheet) itself.
            new Middleware('menu:staff_attendance', only: ['index', 'save']),
            new Middleware('menu:staff_attendance,approve', only: ['approve', 'reopen']),
        ];
    }

    public function index(Request $request): View
    {
        $schoolId = (int) $this->currentSchool()->getKey();
        $date = $this->requestedDate($request);
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $type = EmployeeType::tryFrom($request->string('type')->toString());

        $sheet = StaffAttendanceSheet::query()
            ->with(['takenBy:id,name', 'approvedBy:id,name'])
            ->where('school_id', $schoolId)
            ->whereDate('date', $date->toDateString())
            ->first();

        $employees = Employee::query()
            ->where('school_id', $schoolId)
            // People who have left stay listed only for days they were already marked.
            ->where(fn (Builder $query) => $query
                ->where('status', EmployeeStatus::Active)
                ->when($sheet !== null, fn (Builder $query) => $query->orWhereIn('id', $sheet->records()->select('employee_id'))))
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('employee_no', 'like', "%{$search}%")
                ->orWhere('designation', 'like', "%{$search}%")))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(50, ['id', 'employee_no', 'type', 'name', 'designation', 'status'])
            ->withQueryString();

        $records = $sheet === null
            ? collect()
            : $sheet->records()->whereIn('employee_id', $employees->getCollection()->modelKeys())->get()->keyBy('employee_id');

        return view('school.staff-attendance.index', [
            'school' => $this->currentSchool(),
            'date' => $date,
            'today' => $this->today(),
            'holiday' => Holiday::coveringDay($schoolId, $date->toDateString()),
            'sheet' => $sheet,
            'markedCount' => $sheet?->records()->count() ?? 0,
            'employees' => $employees,
            'records' => $records,
            'search' => $search,
            'selectedType' => $type,
            'types' => EmployeeType::cases(),
            'statuses' => AttendanceStatus::cases(),
            // A short reminder for approvers; the full history is in the reports.
            'pendingSheets' => StaffAttendanceSheet::query()
                ->where('school_id', $schoolId)
                ->where('status', AttendanceSheetStatus::Submitted)
                ->orderByDesc('date')
                ->limit(10)
                ->get(['id', 'date']),
        ]);
    }

    public function save(StaffAttendanceRequest $request): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool()->getKey();
        $date = $this->parseDate((string) $request->validated('date'));

        if ($date === null) {
            throw ValidationException::withMessages(['date' => 'Choose a valid date.']);
        }

        $this->ensureMarkable($date);

        $sheet = StaffAttendanceSheet::query()->where('school_id', $schoolId)->whereDate('date', $date->toDateString())->first();
        $this->ensureCanSave($request, $sheet, 'staff_attendance');

        $rows = $request->validated('attendance');
        $known = Employee::query()->where('school_id', $schoolId)->whereIn('id', array_keys($rows))->pluck('id')->flip();
        $rows = array_intersect_key($rows, $known->all());

        if ($rows === []) {
            throw ValidationException::withMessages(['attendance' => 'There is nobody to mark.']);
        }

        $isNew = $sheet === null;

        $sheet = DB::transaction(function () use ($sheet, $rows, $schoolId, $date, $request): StaffAttendanceSheet {
            $sheet ??= StaffAttendanceSheet::query()->create([
                'school_id' => $schoolId,
                'date' => $date->toDateString(),
                'status' => AttendanceSheetStatus::Submitted,
                'taken_by' => $request->user()?->getKey(),
            ]);
            $sheet->touch();

            foreach ($rows as $employeeId => $row) {
                StaffAttendance::query()->updateOrCreate(
                    ['sheet_id' => $sheet->getKey(), 'employee_id' => (int) $employeeId],
                    [
                        'school_id' => $schoolId,
                        'date' => $date->toDateString(),
                        'status' => $row['status'],
                        'check_in' => $row['check_in'] ?? null,
                        'check_out' => $row['check_out'] ?? null,
                        'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
                    ],
                );
            }

            return $sheet;
        });

        $this->recordSheet($isNew ? 'staff_attendance.submitted' : 'staff_attendance.updated', $request, $sheet, [
            'marked' => count($rows),
            'absent' => count(array_filter($rows, fn (array $row): bool => $row['status'] === AttendanceStatus::Absent->value)),
        ]);

        return redirect()
            ->route('school.staff-attendance.index', array_filter([
                'date' => $date->toDateString(),
                'type' => EmployeeType::tryFrom($request->string('filter_type')->toString())?->value,
                'search' => mb_substr(trim($request->string('filter_search')->toString()), 0, 100),
                'page' => max(0, $request->integer('filter_page')),
            ]))
            ->with('status', 'Staff attendance saved.');
    }

    public function approve(Request $request, StaffAttendanceSheet $sheet): RedirectResponse
    {
        $this->approveSheet($request, $sheet, 'staff_attendance.approved');

        return back()->with('status', 'Staff attendance approved and locked.');
    }

    public function reopen(Request $request, StaffAttendanceSheet $sheet): RedirectResponse
    {
        $this->reopenSheet($request, $sheet, 'staff_attendance.reopened');

        return back()->with('status', 'Staff attendance reopened for changes.');
    }
}
