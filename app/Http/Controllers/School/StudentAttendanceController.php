<?php

namespace App\Http\Controllers\School;

use App\Enums\AttendanceSheetStatus;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\School\Concerns\TakesAttendance;
use App\Http\Requests\School\StudentAttendanceRequest;
use App\Models\AcademicSession;
use App\Models\Holiday;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentAttendanceSheet;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Daily attendance of one section: teachers submit, an approver locks it.
 */
class StudentAttendanceController extends Controller implements HasMiddleware
{
    use TakesAttendance;

    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            // Saving checks create (new sheet) or edit (existing sheet) itself.
            new Middleware('menu:student_attendance', only: ['index', 'save']),
            new Middleware('menu:student_attendance,approve', only: ['approve', 'reopen']),
        ];
    }

    public function index(Request $request): View
    {
        $schoolId = (int) $this->currentSchool()->getKey();

        $sessions = AcademicSession::query()->where('school_id', $schoolId)->orderByDesc('starts_on')->get(['id', 'name', 'is_current', 'starts_on', 'ends_on']);
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
        $section = $sections->firstWhere('id', $request->integer('section_id'));

        $date = $this->requestedDate($request);
        $sheet = null;
        $students = new Collection;
        $records = collect();

        if ($session !== null && $section !== null) {
            $sheet = StudentAttendanceSheet::query()
                ->with(['takenBy:id,name', 'approvedBy:id,name'])
                ->where('section_id', $section->getKey())
                ->whereDate('date', $date->toDateString())
                ->first();
            $records = $sheet?->records()->get()->keyBy('student_id') ?? collect();
            $students = $this->studentsOf($session, $section, $records->keys()->all());
        }

        $sheetStatus = AttendanceSheetStatus::tryFrom($request->string('status')->toString());

        return view('school.student-attendance.index', [
            'school' => $this->currentSchool(),
            'sessions' => $sessions,
            'session' => $session,
            'sections' => $sections,
            'section' => $section,
            'date' => $date,
            'today' => $this->today(),
            'holiday' => Holiday::coveringDay($schoolId, $date->toDateString()),
            'outsideSession' => $session !== null && ($date->lt($session->starts_on) || $date->gt($session->ends_on)),
            'sheet' => $sheet,
            'students' => $students,
            'records' => $records,
            'statuses' => AttendanceStatus::cases(),
            'sheetStatuses' => AttendanceSheetStatus::cases(),
            'selectedSheetStatus' => $sheetStatus,
            // Without a section the page lists recent sheets, e.g. for an approver.
            'sheets' => $section === null ? $this->sheetList($schoolId, $session, $sheetStatus) : null,
        ]);
    }

    public function save(StudentAttendanceRequest $request): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool()->getKey();
        $session = AcademicSession::query()->findOrFail($request->integer('academic_session_id'));
        $section = Section::query()->findOrFail($request->integer('section_id'));
        $date = $this->parseDate((string) $request->validated('date'));

        if ($date === null) {
            throw ValidationException::withMessages(['date' => 'Choose a valid date.']);
        }

        if ($date->lt($session->starts_on) || $date->gt($session->ends_on)) {
            throw ValidationException::withMessages(['date' => "The date is outside the {$session->name} session."]);
        }

        $this->ensureMarkable($date);

        $sheet = StudentAttendanceSheet::query()->where('section_id', $section->getKey())->whereDate('date', $date->toDateString())->first();
        $this->ensureCanSave($request, $sheet, 'student_attendance');

        // Only students enrolled in this section for this session can be marked.
        $enrolled = DB::table('enrollments')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->getKey())
            ->where('section_id', $section->getKey())
            ->pluck('student_id')
            ->flip();
        $rows = array_intersect_key($request->validated('attendance'), $enrolled->all());

        if ($rows === []) {
            throw ValidationException::withMessages(['attendance' => 'There are no students to mark.']);
        }

        $isNew = $sheet === null;

        $sheet = DB::transaction(function () use ($sheet, $rows, $schoolId, $session, $section, $date, $request): StudentAttendanceSheet {
            $sheet ??= StudentAttendanceSheet::query()->create([
                'school_id' => $schoolId,
                'academic_session_id' => $session->getKey(),
                'section_id' => $section->getKey(),
                'date' => $date->toDateString(),
                'status' => AttendanceSheetStatus::Submitted,
                'taken_by' => $request->user()?->getKey(),
            ]);
            $sheet->touch();

            foreach ($rows as $studentId => $row) {
                StudentAttendance::query()->updateOrCreate(
                    ['sheet_id' => $sheet->getKey(), 'student_id' => (int) $studentId],
                    [
                        'school_id' => $schoolId,
                        'date' => $date->toDateString(),
                        'status' => $row['status'],
                        'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
                    ],
                );
            }

            return $sheet;
        });

        $this->recordSheet($isNew ? 'student_attendance.submitted' : 'student_attendance.updated', $request, $sheet, [
            'section_id' => $section->getKey(),
            'marked' => count($rows),
            'absent' => count(array_filter($rows, fn (array $row): bool => $row['status'] === AttendanceStatus::Absent->value)),
        ]);

        return redirect()
            ->route('school.student-attendance.index', $this->sheetQuery($sheet))
            ->with('status', 'Attendance saved.');
    }

    public function approve(Request $request, StudentAttendanceSheet $sheet): RedirectResponse
    {
        $this->approveSheet($request, $sheet, 'student_attendance.approved');

        return back()->with('status', 'Attendance approved and locked.');
    }

    public function reopen(Request $request, StudentAttendanceSheet $sheet): RedirectResponse
    {
        $this->reopenSheet($request, $sheet, 'student_attendance.reopened');

        return back()->with('status', 'Attendance reopened for changes.');
    }

    /**
     * Enrolled students in roll-number order. Students who have left stay on the list
     * only when this sheet already has their attendance.
     *
     * @param  array<int, int>  $markedIds
     * @return Collection<int, Student>
     */
    private function studentsOf(AcademicSession $session, Section $section, array $markedIds): Collection
    {
        return Student::query()
            ->join('enrollments', 'enrollments.student_id', '=', 'students.id')
            ->where('enrollments.school_id', $this->currentSchool()->getKey())
            ->where('enrollments.academic_session_id', $session->getKey())
            ->where('enrollments.section_id', $section->getKey())
            ->where(fn (Builder $query) => $query
                ->where('students.status', StudentStatus::Active)
                ->orWhereIn('students.id', $markedIds))
            ->orderByRaw('enrollments.roll_no IS NULL')
            ->orderByRaw('LENGTH(enrollments.roll_no)')
            ->orderBy('enrollments.roll_no')
            ->orderBy('students.first_name')
            ->get(['students.id', 'students.admission_no', 'students.first_name', 'students.last_name', 'students.status', 'enrollments.roll_no']);
    }

    /**
     * @return LengthAwarePaginator<int, StudentAttendanceSheet>
     */
    private function sheetList(int $schoolId, ?AcademicSession $session, ?AttendanceSheetStatus $status): LengthAwarePaginator
    {
        return StudentAttendanceSheet::query()
            ->where('school_id', $schoolId)
            ->when($session !== null, fn (Builder $query) => $query->where('academic_session_id', $session->getKey()))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->with(['section:id,name,class_id', 'section.schoolClass:id,name', 'takenBy:id,name'])
            ->withCount([
                'records',
                'records as absent_count' => fn (Builder $query) => $query->where('status', AttendanceStatus::Absent),
            ])
            ->orderByDesc('date')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return array<string, int|string>
     */
    private function sheetQuery(StudentAttendanceSheet $sheet): array
    {
        return [
            'academic_session_id' => $sheet->academic_session_id,
            'section_id' => $sheet->section_id,
            'date' => $sheet->date->toDateString(),
        ];
    }
}
