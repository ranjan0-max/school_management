<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\AcademicSessionRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\TimetableEntry;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcademicSessionController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:academic_sessions', only: ['index']),
            new Middleware('menu:academic_sessions,create', only: ['create', 'store']),
            new Middleware('menu:academic_sessions,edit', only: ['edit', 'update', 'makeCurrent']),
            new Middleware('menu:academic_sessions,delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);

        $sessions = AcademicSession::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('starts_on')
            ->paginate(15)
            ->withQueryString();

        return view('school.academic-sessions.index', [
            'school' => $this->currentSchool(),
            'sessions' => $sessions,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('school.academic-sessions.form', [
            'school' => $this->currentSchool(),
            'session' => new AcademicSession,
        ]);
    }

    public function store(AcademicSessionRequest $request): RedirectResponse
    {
        $session = DB::transaction(function () use ($request): AcademicSession {
            $session = AcademicSession::query()->create([
                ...$request->validated(),
                'school_id' => $this->currentSchool()->getKey(),
            ]);

            if ($session->is_current) {
                $this->clearOtherCurrent($session);
            }

            return $session;
        });

        $this->record('academic_session.created', $request, $session);

        return redirect()->route('school.academic-sessions.index')->with('status', 'Academic session created.');
    }

    public function edit(AcademicSession $academicSession): View
    {
        $this->ensureCurrentSchool($academicSession);

        return view('school.academic-sessions.form', [
            'school' => $this->currentSchool(),
            'session' => $academicSession,
        ]);
    }

    public function update(AcademicSessionRequest $request, AcademicSession $academicSession): RedirectResponse
    {
        $this->ensureCurrentSchool($academicSession);

        DB::transaction(function () use ($request, $academicSession): void {
            $academicSession->update($request->validated());

            if ($academicSession->is_current) {
                $this->clearOtherCurrent($academicSession);
            }
        });

        $this->record('academic_session.updated', $request, $academicSession);

        return redirect()->route('school.academic-sessions.index')->with('status', 'Academic session updated.');
    }

    public function makeCurrent(Request $request, AcademicSession $academicSession): RedirectResponse
    {
        $this->ensureCurrentSchool($academicSession);

        DB::transaction(function () use ($academicSession): void {
            $academicSession->update(['is_current' => true]);
            $this->clearOtherCurrent($academicSession);
        });

        $this->record('academic_session.made_current', $request, $academicSession);

        return back()->with('status', "{$academicSession->name} is now the current session.");
    }

    public function destroy(Request $request, AcademicSession $academicSession): RedirectResponse
    {
        $this->ensureCurrentSchool($academicSession);

        if ($academicSession->is_current) {
            return back()->with('error', 'The current session cannot be deleted. Make another session current first.');
        }

        if (Enrollment::query()->where('academic_session_id', $academicSession->getKey())->exists()
            || TimetableEntry::query()->where('academic_session_id', $academicSession->getKey())->exists()) {
            return back()->with('error', "{$academicSession->name} has student records or a timetable and cannot be deleted.");
        }

        $academicSession->delete();
        $this->record('academic_session.deleted', $request, $academicSession);

        return back()->with('status', 'Academic session deleted.');
    }

    /**
     * Only one session per school can be current.
     */
    private function clearOtherCurrent(AcademicSession $current): void
    {
        AcademicSession::query()
            ->where('school_id', $current->school_id)
            ->whereKeyNot($current->getKey())
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    private function record(string $event, Request $request, AcademicSession $session): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: AcademicSession::class,
            auditableId: $session->getKey(),
            newValues: [
                'name' => $session->name,
                'starts_on' => $session->starts_on->toDateString(),
                'ends_on' => $session->ends_on->toDateString(),
                'is_current' => $session->is_current,
            ],
        );
    }
}
