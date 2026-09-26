<?php

namespace App\Http\Controllers\School;

use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StudentRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Support\Audit\AuditLogger;
use App\Support\Numbering\SchoolNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Students are never deleted; set the status to Left or Passed out instead.
 * The form places the student in a section of the current academic session.
 */
class StudentController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:students', only: ['index']),
            new Middleware('menu:students,create', only: ['create', 'store']),
            new Middleware('menu:students,edit', only: ['edit', 'update']),
        ];
    }

    public function index(Request $request): View
    {
        $schoolId = $this->currentSchool()->getKey();
        $sessions = AcademicSession::query()->where('school_id', $schoolId)->orderByDesc('starts_on')->get(['id', 'name', 'is_current']);
        $session = $sessions->firstWhere('id', $request->integer('academic_session_id'))
            ?? $sessions->firstWhere('is_current', true)
            ?? $sessions->first();

        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $classId = $request->integer('class_id') ?: null;
        $sectionId = $request->integer('section_id') ?: null;
        $status = StudentStatus::tryFrom($request->string('status')->toString());

        $students = Student::query()
            ->where('students.school_id', $schoolId)
            ->leftJoin('enrollments', function (JoinClause $join) use ($session): void {
                $join->on('enrollments.student_id', '=', 'students.id')
                    ->where('enrollments.academic_session_id', $session?->getKey() ?? 0);
            })
            ->leftJoin('sections', 'sections.id', '=', 'enrollments.section_id')
            ->leftJoin('classes', 'classes.id', '=', 'sections.class_id')
            ->when($status !== null, fn (Builder $query) => $query->where('students.status', $status))
            ->when($classId !== null, fn (Builder $query) => $query->where('sections.class_id', $classId))
            ->when($sectionId !== null, fn (Builder $query) => $query->where('enrollments.section_id', $sectionId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $query) => $query
                    ->where('students.first_name', 'like', "%{$search}%")
                    ->orWhere('students.last_name', 'like', "%{$search}%")
                    ->orWhere('students.admission_no', 'like', "%{$search}%")
                    ->orWhere('students.phone', 'like', "%{$search}%"));
            })
            ->orderBy('classes.sort_order')
            ->orderBy('sections.name')
            ->orderBy('students.first_name')
            ->select(['students.*', 'enrollments.roll_no', 'sections.name as section_name', 'classes.name as class_name'])
            ->paginate(20)
            ->withQueryString();

        return view('school.students.index', [
            'school' => $this->currentSchool(),
            'students' => $students,
            'sessions' => $sessions,
            'session' => $session,
            'classes' => SchoolClass::query()->where('school_id', $schoolId)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'sections' => $this->sectionOptions(),
            'search' => $search,
            'selectedClassId' => $classId,
            'selectedSectionId' => $sectionId,
            'selectedStatus' => $status,
            'statuses' => StudentStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('school.students.form', $this->formData(new Student(['status' => StudentStatus::Active]), null, []));
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $school = $this->currentSchool();
        $attributes = $request->validated();
        $currentSession = $this->currentSession();
        $this->ensureRollNoIsFree($attributes, $currentSession, null);

        $student = DB::transaction(function () use ($attributes, $school, $currentSession): Student {
            $year = isset($attributes['admission_date']) ? substr((string) $attributes['admission_date'], 0, 4) : now()->format('Y');

            $student = Student::query()->create([
                ...$this->studentFields($attributes),
                'school_id' => $school->getKey(),
                'admission_no' => $attributes['admission_no'] ?? SchoolNumber::next($school, 'students', 'admission_no', "{$school->admissionPrefix()}-{$year}-"),
            ]);

            $this->syncEnrollment($student, $currentSession, $attributes);
            $this->syncGuardians($student, $attributes['guardians'] ?? []);

            return $student;
        });

        $this->record('student.created', $request, $student);

        return redirect()->route('school.students.index')
            ->with('status', "Student {$student->fullName()} added ({$student->admission_no}).");
    }

    public function edit(Student $student): View
    {
        $this->ensureCurrentSchool($student);

        $enrollment = ($session = $this->currentSession()) === null
            ? null
            : $student->enrollments()->where('academic_session_id', $session->getKey())->first();

        $guardians = $student->guardians()->orderByDesc('guardian_student.is_primary')->get()
            ->map(fn (Guardian $guardian): array => [
                'name' => $guardian->name,
                'phone' => $guardian->phone,
                'email' => $guardian->email,
                'relation' => $guardian->pivot->relation ?? null,
            ])->all();

        return view('school.students.form', $this->formData($student, $enrollment, $guardians));
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $this->ensureCurrentSchool($student);

        $attributes = $request->validated();
        $currentSession = $this->currentSession();
        $this->ensureRollNoIsFree($attributes, $currentSession, $student);

        DB::transaction(function () use ($student, $attributes, $currentSession): void {
            $student->update([
                ...$this->studentFields($attributes),
                'admission_no' => $attributes['admission_no'] ?? $student->admission_no,
            ]);

            $this->syncEnrollment($student, $currentSession, $attributes);
            $this->syncGuardians($student, $attributes['guardians'] ?? []);
        });

        $this->record('student.updated', $request, $student);

        return redirect()->route('school.students.index')->with('status', "Student {$student->fullName()} updated.");
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function studentFields(array $attributes): array
    {
        return collect($attributes)->only([
            'first_name', 'last_name', 'gender', 'date_of_birth', 'blood_group',
            'phone', 'email', 'address', 'admission_date', 'status',
        ])->all();
    }

    /**
     * Places the student in the chosen section for the current session, or removes
     * that session's placement when no section is chosen. Other sessions are untouched.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function syncEnrollment(Student $student, ?AcademicSession $session, array $attributes): void
    {
        if ($session === null) {
            return;
        }

        if (empty($attributes['section_id'])) {
            $student->enrollments()->where('academic_session_id', $session->getKey())->delete();

            return;
        }

        Enrollment::query()->updateOrCreate(
            ['student_id' => $student->getKey(), 'academic_session_id' => $session->getKey()],
            [
                'school_id' => $student->school_id,
                'section_id' => (int) $attributes['section_id'],
                'roll_no' => $attributes['roll_no'] ?? null,
            ],
        );
    }

    /**
     * Guardians are matched by phone inside the school, so siblings share one record.
     * The first filled guardian becomes the primary contact.
     *
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function syncGuardians(Student $student, array $rows): void
    {
        $links = [];

        foreach ($rows as $row) {
            if (($row['name'] ?? null) === null) {
                continue;
            }

            $guardian = $row['phone'] !== null
                ? Guardian::query()->where('school_id', $student->school_id)->where('phone', $row['phone'])->first()
                : null;

            if ($guardian === null) {
                $guardian = Guardian::query()->create([
                    'school_id' => $student->school_id,
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                ]);
            } else {
                // Keep the shared record; only fill in details that are still empty.
                $guardian->fill(array_filter([
                    'name' => $row['name'],
                    'email' => $guardian->email === null ? $row['email'] : null,
                ]))->save();
            }

            $links[$guardian->getKey()] = [
                'school_id' => $student->school_id,
                'relation' => $row['relation'] ?? null,
                'is_primary' => $links === [],
            ];
        }

        $student->guardians()->sync($links);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ensureRollNoIsFree(array $attributes, ?AcademicSession $session, ?Student $student): void
    {
        if ($session === null || empty($attributes['section_id']) || empty($attributes['roll_no'])) {
            return;
        }

        $taken = Enrollment::query()
            ->where('academic_session_id', $session->getKey())
            ->where('section_id', (int) $attributes['section_id'])
            ->where('roll_no', $attributes['roll_no'])
            ->when($student !== null, fn (Builder $query) => $query->where('student_id', '!=', $student->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['roll_no' => 'This roll number is already used in the selected section.']);
        }
    }

    private function currentSession(): ?AcademicSession
    {
        return AcademicSession::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->where('is_current', true)
            ->first();
    }

    /**
     * @return Collection<int, Section>
     */
    private function sectionOptions(): Collection
    {
        return Section::query()
            ->where('sections.school_id', $this->currentSchool()->getKey())
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->orderBy('classes.sort_order')
            ->orderBy('classes.name')
            ->orderBy('sections.name')
            ->get(['sections.id', 'sections.class_id', 'sections.name', 'classes.name as class_name']);
    }

    /**
     * @param  array<int, array<string, string|null>>  $guardians
     * @return array<string, mixed>
     */
    private function formData(Student $student, ?Enrollment $enrollment, array $guardians): array
    {
        return [
            'school' => $this->currentSchool(),
            'student' => $student,
            'enrollment' => $enrollment,
            'currentSession' => $this->currentSession(),
            'sections' => $this->sectionOptions(),
            'guardians' => array_pad($guardians, StudentRequest::MAX_GUARDIANS, ['name' => null, 'phone' => null, 'email' => null, 'relation' => null]),
            'genders' => Gender::cases(),
            'statuses' => StudentStatus::cases(),
            'relations' => GuardianRelation::cases(),
        ];
    }

    private function record(string $event, Request $request, Student $student): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Student::class,
            auditableId: $student->getKey(),
            // Student personal data stays out of the audit log.
            newValues: ['admission_no' => $student->admission_no, 'status' => $student->status->value],
        );
    }
}
