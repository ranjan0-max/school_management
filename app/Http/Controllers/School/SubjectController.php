<?php

namespace App\Http\Controllers\School;

use App\Enums\SubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\SubjectRequest;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SubjectController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:subjects', only: ['index']),
            new Middleware('menu:subjects,create', only: ['create', 'store']),
            new Middleware('menu:subjects,edit', only: ['edit', 'update']),
            new Middleware('menu:subjects,delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $type = SubjectType::tryFrom($request->string('type')->toString());

        $subjects = Subject::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->withCount('classes')
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('school.subjects.index', [
            'school' => $this->currentSchool(),
            'subjects' => $subjects,
            'search' => $search,
            'selectedType' => $type,
            'types' => SubjectType::cases(),
        ]);
    }

    public function create(): View
    {
        return view('school.subjects.form', [
            'school' => $this->currentSchool(),
            'subject' => new Subject(['type' => SubjectType::Theory]),
            'types' => SubjectType::cases(),
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::query()->create([
            ...$request->validated(),
            'school_id' => $this->currentSchool()->getKey(),
        ]);

        $this->record('subject.created', $request, $subject);

        return redirect()->route('school.subjects.index')->with('status', 'Subject created.');
    }

    public function edit(Subject $subject): View
    {
        $this->ensureCurrentSchool($subject);

        return view('school.subjects.form', [
            'school' => $this->currentSchool(),
            'subject' => $subject,
            'types' => SubjectType::cases(),
        ]);
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $this->ensureCurrentSchool($subject);

        $subject->update($request->validated());
        $this->record('subject.updated', $request, $subject);

        return redirect()->route('school.subjects.index')->with('status', 'Subject updated.');
    }

    /**
     * Removing a subject also removes it from the classes that teach it.
     */
    public function destroy(Request $request, Subject $subject): RedirectResponse
    {
        $this->ensureCurrentSchool($subject);

        if (TimetableEntry::query()->where('subject_id', $subject->getKey())->exists()) {
            return back()->with('error', "{$subject->name} is used in a timetable. Remove it from the timetable first.");
        }

        $subject->delete();
        $this->record('subject.deleted', $request, $subject);

        return back()->with('status', 'Subject deleted.');
    }

    private function record(string $event, Request $request, Subject $subject): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Subject::class,
            auditableId: $subject->getKey(),
            newValues: [
                'name' => $subject->name,
                'code' => $subject->code,
                'type' => $subject->type->value,
            ],
        );
    }
}
