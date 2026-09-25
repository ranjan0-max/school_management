<?php

namespace App\Http\Controllers\School;

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\TimetableRequest;
use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\Period;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Weekly timetable of one section for one academic session, edited as a days × periods grid.
 */
class TimetableEntryController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:timetable', only: ['index']),
            new Middleware('menu:timetable,edit', only: ['update']),
        ];
    }

    public function index(Request $request): View
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
        $section = $sections->firstWhere('id', $request->integer('section_id'));

        $entries = $session !== null && $section !== null
            ? TimetableEntry::query()
                ->where('academic_session_id', $session->getKey())
                ->where('section_id', $section->getKey())
                ->get()
                ->groupBy('day')
                ->map(fn (Collection $dayEntries) => $dayEntries->keyBy('period_id'))
            : collect();

        return view('school.timetable.index', [
            'school' => $this->currentSchool(),
            'sessions' => $sessions,
            'session' => $session,
            'sections' => $sections,
            'section' => $section,
            'days' => TimetableEntry::DAYS,
            'periods' => Period::query()->where('school_id', $schoolId)->orderBy('sort_order')->orderBy('starts_at')->get(),
            'subjects' => $section === null ? new Collection : $this->subjectsFor($section),
            'teachers' => Employee::query()
                ->where('school_id', $schoolId)
                ->where('type', EmployeeType::Teacher)
                ->where('status', EmployeeStatus::Active)
                ->orderBy('name')
                ->get(['id', 'name', 'employee_no']),
            'entries' => $entries,
        ]);
    }

    public function update(TimetableRequest $request): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool()->getKey();
        $sessionId = $request->integer('academic_session_id');
        $sectionId = $request->integer('section_id');
        $slots = $request->validated('slots', []);

        $rows = [];

        foreach (Period::query()->where('school_id', $schoolId)->where('is_break', false)->get() as $period) {
            foreach (array_keys(TimetableEntry::DAYS) as $day) {
                $slot = $slots[$day][$period->getKey()] ?? [];
                $subjectId = isset($slot['subject_id']) ? (int) $slot['subject_id'] : null;
                $teacherId = isset($slot['teacher_id']) ? (int) $slot['teacher_id'] : null;

                if ($subjectId === null && $teacherId === null) {
                    continue;
                }

                $rows[] = [
                    'school_id' => $schoolId,
                    'academic_session_id' => $sessionId,
                    'section_id' => $sectionId,
                    'day' => $day,
                    'period_id' => (int) $period->getKey(),
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                ];
            }
        }

        $this->ensureTeachersAreFree($rows, $sessionId, $sectionId);

        DB::transaction(function () use ($rows, $sessionId, $sectionId): void {
            TimetableEntry::query()->where('academic_session_id', $sessionId)->where('section_id', $sectionId)->delete();

            foreach ($rows as $row) {
                TimetableEntry::query()->create($row);
            }
        });

        $this->audit->record(
            event: 'timetable.updated',
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Section::class,
            auditableId: $sectionId,
            newValues: ['academic_session_id' => $sessionId, 'filled_slots' => count($rows)],
        );

        return redirect()
            ->route('school.timetable.index', ['academic_session_id' => $sessionId, 'section_id' => $sectionId])
            ->with('status', 'Timetable saved.');
    }

    /**
     * A teacher cannot take two sections in the same period of the same day.
     *
     * @param  array<int, array<string, int|null>>  $rows
     */
    private function ensureTeachersAreFree(array $rows, int $sessionId, int $sectionId): void
    {
        $messages = [];

        foreach ($rows as $row) {
            if ($row['teacher_id'] === null) {
                continue;
            }

            $clash = TimetableEntry::query()
                ->with(['teacher:id,name', 'period:id,name', 'section:id,name,class_id', 'section.schoolClass:id,name'])
                ->where('academic_session_id', $sessionId)
                ->where('section_id', '!=', $sectionId)
                ->where('teacher_id', $row['teacher_id'])
                ->where('day', $row['day'])
                ->where('period_id', $row['period_id'])
                ->first();

            if ($clash !== null) {
                $messages[] = sprintf(
                    '%s already teaches %s – %s on %s, %s.',
                    $clash->teacher?->name,
                    $clash->section?->schoolClass?->name,
                    $clash->section?->name,
                    TimetableEntry::DAYS[$clash->day] ?? '',
                    $clash->period?->name,
                );
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['slots' => $messages]);
        }
    }

    /**
     * The class's own subjects when set, otherwise every subject of the school.
     *
     * @return Collection<int, Subject>
     */
    private function subjectsFor(Section $section): Collection
    {
        $classSubjects = Subject::query()
            ->whereHas('classes', fn ($query) => $query->whereKey($section->class_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return $classSubjects->isNotEmpty()
            ? $classSubjects
            : Subject::query()->where('school_id', $this->currentSchool()->getKey())->orderBy('name')->get(['id', 'name', 'code']);
    }
}
