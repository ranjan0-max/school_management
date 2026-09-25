<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\SectionRequest;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\TimetableEntry;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SectionController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:sections', only: ['index']),
            new Middleware('menu:sections,create', only: ['create', 'store']),
            new Middleware('menu:sections,edit', only: ['edit', 'update']),
            new Middleware('menu:sections,delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $classId = $request->integer('class_id') ?: null;

        $sections = Section::query()
            ->where('sections.school_id', $this->currentSchool()->getKey())
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->when($classId !== null, fn (Builder $query) => $query->where('sections.class_id', $classId))
            ->when($search !== '', fn (Builder $query) => $query->where('sections.name', 'like', "%{$search}%"))
            ->orderBy('classes.sort_order')
            ->orderBy('classes.name')
            ->orderBy('sections.name')
            ->select(['sections.*', 'classes.name as class_name'])
            ->paginate(20)
            ->withQueryString();

        return view('school.sections.index', [
            'school' => $this->currentSchool(),
            'sections' => $sections,
            'classes' => $this->schoolClasses(),
            'search' => $search,
            'selectedClassId' => $classId,
        ]);
    }

    public function create(Request $request): View
    {
        return view('school.sections.form', [
            'school' => $this->currentSchool(),
            'section' => new Section(['class_id' => $request->integer('class_id') ?: null]),
            'classes' => $this->schoolClasses(),
        ]);
    }

    public function store(SectionRequest $request): RedirectResponse
    {
        $section = Section::query()->create([
            ...$request->validated(),
            'school_id' => $this->currentSchool()->getKey(),
        ]);

        $this->record('section.created', $request, $section);

        return redirect()
            ->route('school.sections.index', ['class_id' => $section->class_id])
            ->with('status', 'Section created.');
    }

    public function edit(Section $section): View
    {
        $this->ensureCurrentSchool($section);

        return view('school.sections.form', [
            'school' => $this->currentSchool(),
            'section' => $section,
            'classes' => $this->schoolClasses(),
        ]);
    }

    public function update(SectionRequest $request, Section $section): RedirectResponse
    {
        $this->ensureCurrentSchool($section);

        $section->update($request->validated());
        $this->record('section.updated', $request, $section);

        return redirect()
            ->route('school.sections.index', ['class_id' => $section->class_id])
            ->with('status', 'Section updated.');
    }

    public function destroy(Request $request, Section $section): RedirectResponse
    {
        $this->ensureCurrentSchool($section);

        if (Enrollment::query()->where('section_id', $section->getKey())->exists()
            || TimetableEntry::query()->where('section_id', $section->getKey())->exists()) {
            return back()->with('error', "Section {$section->name} has students or a timetable. Move them before deleting it.");
        }

        $section->delete();
        $this->record('section.deleted', $request, $section);

        return back()->with('status', 'Section deleted.');
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function schoolClasses(): Collection
    {
        return SchoolClass::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function record(string $event, Request $request, Section $section): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Section::class,
            auditableId: $section->getKey(),
            newValues: [
                'class_id' => $section->class_id,
                'name' => $section->name,
                'capacity' => $section->capacity,
            ],
        );
    }
}
