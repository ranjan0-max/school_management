<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\SchoolClassRequest;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SchoolClassController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:classes', only: ['index']),
            new Middleware('menu:classes,create', only: ['create', 'store']),
            new Middleware('menu:classes,edit', only: ['edit', 'update']),
            new Middleware('menu:classes,delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);

        $classes = SchoolClass::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->withCount(['sections', 'subjects'])
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('school.classes.index', [
            'school' => $this->currentSchool(),
            'classes' => $classes,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('school.classes.form', [
            'school' => $this->currentSchool(),
            'schoolClass' => new SchoolClass(['sort_order' => $this->nextSortOrder()]),
            'subjects' => $this->schoolSubjects(),
            'selectedSubjectIds' => [],
        ]);
    }

    public function store(SchoolClassRequest $request): RedirectResponse
    {
        $attributes = $request->validated();

        $schoolClass = DB::transaction(function () use ($attributes): SchoolClass {
            $schoolClass = SchoolClass::query()->create([
                'school_id' => $this->currentSchool()->getKey(),
                'name' => $attributes['name'],
                'sort_order' => $attributes['sort_order'],
            ]);

            $this->syncSubjects($schoolClass, $attributes['subject_ids'] ?? []);

            return $schoolClass;
        });

        $this->record('class.created', $request, $schoolClass);

        return redirect()->route('school.classes.index')->with('status', 'Class created.');
    }

    public function edit(SchoolClass $schoolClass): View
    {
        $this->ensureCurrentSchool($schoolClass);

        return view('school.classes.form', [
            'school' => $this->currentSchool(),
            'schoolClass' => $schoolClass,
            'subjects' => $this->schoolSubjects(),
            'selectedSubjectIds' => $schoolClass->subjects()->pluck('subjects.id')->map(fn (mixed $id): int => (int) $id)->all(),
        ]);
    }

    public function update(SchoolClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->ensureCurrentSchool($schoolClass);
        $attributes = $request->validated();

        DB::transaction(function () use ($schoolClass, $attributes): void {
            $schoolClass->update([
                'name' => $attributes['name'],
                'sort_order' => $attributes['sort_order'],
            ]);

            $this->syncSubjects($schoolClass, $attributes['subject_ids'] ?? []);
        });

        $this->record('class.updated', $request, $schoolClass);

        return redirect()->route('school.classes.index')->with('status', 'Class updated.');
    }

    public function destroy(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->ensureCurrentSchool($schoolClass);

        if ($schoolClass->sections()->exists()) {
            return back()->with('error', "{$schoolClass->name} still has sections. Delete or move them first.");
        }

        $schoolClass->delete();
        $this->record('class.deleted', $request, $schoolClass);

        return back()->with('status', 'Class deleted.');
    }

    /**
     * @param  array<int, mixed>  $subjectIds  Already validated to belong to the current school.
     */
    private function syncSubjects(SchoolClass $schoolClass, array $subjectIds): void
    {
        $schoolId = $this->currentSchool()->getKey();

        $schoolClass->subjects()->sync(
            collect($subjectIds)->mapWithKeys(fn (mixed $id): array => [(int) $id => ['school_id' => $schoolId]])->all(),
        );
    }

    /**
     * @return Collection<int, Subject>
     */
    private function schoolSubjects(): Collection
    {
        return Subject::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);
    }

    private function nextSortOrder(): int
    {
        return (int) SchoolClass::query()->where('school_id', $this->currentSchool()->getKey())->max('sort_order') + 1;
    }

    private function record(string $event, Request $request, SchoolClass $schoolClass): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: SchoolClass::class,
            auditableId: $schoolClass->getKey(),
            newValues: [
                'name' => $schoolClass->name,
                'sort_order' => $schoolClass->sort_order,
                'subject_ids' => $schoolClass->subjects()->pluck('subjects.id')->all(),
            ],
        );
    }
}
