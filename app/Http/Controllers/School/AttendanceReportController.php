<?php

namespace App\Http\Controllers\School;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeType;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceSheet;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentAttendanceSheet;
use App\Support\Attendance\MonthlyRegister;
use App\Support\Attendance\PersonMonth;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monthly attendance registers for a section's students and for staff, with CSV export.
 */
class AttendanceReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('menu:attendance_reports', only: ['students', 'staff']),
            new Middleware('menu:attendance_reports,export', only: ['studentsExport', 'staffExport']),
        ];
    }

    public function students(Request $request): View
    {
        [$sessions, $session, $sections, $section, $month] = $this->studentFilters($request);
        $register = null;
        $students = null;
        $rows = [];

        if ($session !== null && $section !== null) {
            $register = $this->studentRegister($section, $month);
            $students = $this->studentQuery($session, $section)->paginate(50)->withQueryString();
            $rows = $this->studentRows($register, $students->getCollection()->modelKeys());
        }

        return view('school.attendance-reports.students', [
            'school' => $this->currentSchool(),
            'sessions' => $sessions,
            'session' => $session,
            'sections' => $sections,
            'section' => $section,
            'month' => $month,
            'register' => $register,
            'students' => $students,
            'rows' => $rows,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function studentsExport(Request $request): StreamedResponse
    {
        [, $session, , $section, $month] = $this->studentFilters($request);
        abort_if($session === null || $section === null, 404);

        $register = $this->studentRegister($section, $month);
        $students = $this->studentQuery($session, $section)->get();
        $rows = $this->studentRows($register, $students->modelKeys());
        $name = Str::slug("attendance {$section->class_name} {$section->name} {$month->format('Y-m')}").'.csv';

        return $this->csv($name, $register, ['Roll no', 'Admission no', 'Student'], function () use ($students, $rows, $register): iterable {
            foreach ($students as $student) {
                yield [[$student->roll_no, $student->admission_no, $student->fullName()], $rows[$student->getKey()] ?? $register->empty()];
            }
        });
    }

    public function staff(Request $request): View
    {
        [$month, $type, $search] = $this->staffFilters($request);
        $register = $this->staffRegister($month);
        $employees = $this->staffQuery($type, $search)->paginate(50)->withQueryString();

        return view('school.attendance-reports.staff', [
            'school' => $this->currentSchool(),
            'month' => $month,
            'register' => $register,
            'employees' => $employees,
            'rows' => $this->staffRows($register, $employees->getCollection()->modelKeys()),
            'selectedType' => $type,
            'types' => EmployeeType::cases(),
            'search' => $search,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function staffExport(Request $request): StreamedResponse
    {
        [$month, $type, $search] = $this->staffFilters($request);
        $register = $this->staffRegister($month);
        $query = $this->staffQuery($type, $search);

        return $this->csv("staff-attendance-{$month->format('Y-m')}.csv", $register, ['Employee no', 'Name', 'Type'], function () use ($query, $register): iterable {
            // Records are loaded per batch of 200 people to keep memory flat on large schools.
            foreach ($query->cursor()->chunk(200) as $batch) {
                $rows = $this->staffRows($register, $batch->map(fn (Employee $employee): int => (int) $employee->getKey())->values()->all());

                foreach ($batch as $employee) {
                    yield [[$employee->employee_no, $employee->name, $employee->type->label()], $rows[$employee->getKey()] ?? $register->empty()];
                }
            }
        });
    }

    /**
     * @return array{0: Collection<int, AcademicSession>, 1: ?AcademicSession, 2: Collection<int, Section>, 3: ?Section, 4: CarbonImmutable}
     */
    private function studentFilters(Request $request): array
    {
        $schoolId = $this->currentSchool()->getKey();

        $sessions = AcademicSession::query()->where('school_id', $schoolId)->orderByDesc('starts_on')->get(['id', 'name', 'is_current']);
        $session = $sessions->firstWhere('id', $request->integer('academic_session_id'))
            ?? $sessions->firstWhere('is_current', true)
            ?? $sessions->first();

        $sections = Section::query()
            ->where('sections.school_id', $schoolId)
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->orderBy('classes.sort_order')
            ->orderBy('classes.name')
            ->orderBy('sections.name')
            ->get(['sections.id', 'sections.class_id', 'sections.name', 'classes.name as class_name']);

        return [$sessions, $session, $sections, $sections->firstWhere('id', $request->integer('section_id')), $this->month($request)];
    }

    /**
     * @return array{0: CarbonImmutable, 1: ?EmployeeType, 2: string}
     */
    private function staffFilters(Request $request): array
    {
        return [
            $this->month($request),
            EmployeeType::tryFrom($request->string('type')->toString()),
            mb_substr(trim($request->string('search')->toString()), 0, 100),
        ];
    }

    private function month(Request $request): CarbonImmutable
    {
        $timezone = $this->currentSchool()->timezone ?: config('app.timezone');

        return MonthlyRegister::parseMonth($request->string('month')->toString(), CarbonImmutable::parse(CarbonImmutable::now($timezone)->toDateString()));
    }

    private function studentRegister(Section $section, CarbonImmutable $month): MonthlyRegister
    {
        return MonthlyRegister::for(
            $this->currentSchool(),
            $month,
            StudentAttendanceSheet::query()->where('section_id', $section->getKey()),
        );
    }

    private function staffRegister(CarbonImmutable $month): MonthlyRegister
    {
        $school = $this->currentSchool();

        return MonthlyRegister::for($school, $month, StaffAttendanceSheet::query()->where('school_id', $school->getKey()));
    }

    /**
     * Everyone enrolled in the section this session, in roll-number order.
     *
     * @return Builder<Student>
     */
    private function studentQuery(AcademicSession $session, Section $section): Builder
    {
        return Student::query()
            ->join('enrollments', 'enrollments.student_id', '=', 'students.id')
            ->where('enrollments.school_id', $this->currentSchool()->getKey())
            ->where('enrollments.academic_session_id', $session->getKey())
            ->where('enrollments.section_id', $section->getKey())
            ->orderByRaw('enrollments.roll_no IS NULL')
            ->orderByRaw('LENGTH(enrollments.roll_no)')
            ->orderBy('enrollments.roll_no')
            ->orderBy('students.first_name')
            ->orderBy('students.id')
            ->select(['students.id', 'students.admission_no', 'students.first_name', 'students.last_name', 'students.status', 'enrollments.roll_no']);
    }

    /**
     * @return Builder<Employee>
     */
    private function staffQuery(?EmployeeType $type, string $search): Builder
    {
        return Employee::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('employee_no', 'like', "%{$search}%")))
            ->orderBy('type')
            ->orderBy('name')
            ->select(['id', 'employee_no', 'type', 'name', 'designation', 'status']);
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return array<int, PersonMonth>
     */
    private function studentRows(MonthlyRegister $register, array $studentIds): array
    {
        return $register->summarise(
            StudentAttendance::query()
                ->where('school_id', $this->currentSchool()->getKey())
                ->whereIn('student_id', $studentIds)
                ->whereDate('date', '>=', $register->from())
                ->whereDate('date', '<=', $register->to())
                ->get(['student_id', 'date', 'status']),
            'student_id',
        );
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, PersonMonth>
     */
    private function staffRows(MonthlyRegister $register, array $employeeIds): array
    {
        return $register->summarise(
            StaffAttendance::query()
                ->where('school_id', $this->currentSchool()->getKey())
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('date', '>=', $register->from())
                ->whereDate('date', '<=', $register->to())
                ->get(['employee_id', 'date', 'status']),
            'employee_id',
        );
    }

    /**
     * Streams a register as CSV: identity columns, one column per day, then totals.
     *
     * @param  array<int, string>  $identityHeadings
     * @param  callable(): iterable<array{0: array<int, string|null>, 1: PersonMonth}>  $people
     */
    private function csv(string $filename, MonthlyRegister $register, array $identityHeadings, callable $people): StreamedResponse
    {
        return response()->streamDownload(function () use ($register, $identityHeadings, $people): void {
            $out = fopen('php://output', 'wb');
            // BOM so Excel reads names in any script correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $statuses = AttendanceStatus::cases();
            fputcsv($out, [
                ...$identityHeadings,
                ...array_map(fn (int $day): string => (string) $day, array_keys($register->days())),
                ...array_map(fn (AttendanceStatus $status): string => $status->label(), $statuses),
                'Attendance %',
            ]);

            foreach ($people() as [$identity, $row]) {
                $cells = array_map(fn ($value): string => $this->safeCell((string) $value), $identity);

                foreach (array_keys($register->days()) as $day) {
                    $cells[] = $row->on($day)?->code() ?? (isset($register->holidays[$day]) ? 'H' : '');
                }

                foreach ($statuses as $status) {
                    $cells[] = (string) $row->count($status);
                }

                $cells[] = $row->percentage() === null ? '' : (string) $row->percentage();
                fputcsv($out, $cells);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stops spreadsheet apps from running a name like "=cmd" as a formula.
     */
    private function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
