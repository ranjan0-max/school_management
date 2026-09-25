<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\PeriodRequest;
use App\Models\Period;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Bell schedule used by the timetable; protected by the timetable menu.
 */
class PeriodController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:timetable', only: ['index']),
            new Middleware('menu:timetable,create', only: ['create', 'store']),
            new Middleware('menu:timetable,edit', only: ['edit', 'update']),
            new Middleware('menu:timetable,delete', only: ['destroy']),
        ];
    }

    /**
     * A school has a handful of periods, so the list is not paginated.
     */
    public function index(): View
    {
        return view('school.periods.index', [
            'school' => $this->currentSchool(),
            'periods' => Period::query()
                ->where('school_id', $this->currentSchool()->getKey())
                ->withCount('entries')
                ->orderBy('sort_order')
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $nextOrder = (int) Period::query()->where('school_id', $this->currentSchool()->getKey())->max('sort_order') + 1;

        return view('school.periods.form', [
            'school' => $this->currentSchool(),
            'period' => new Period(['sort_order' => $nextOrder, 'name' => 'Period '.$nextOrder]),
        ]);
    }

    public function store(PeriodRequest $request): RedirectResponse
    {
        $period = Period::query()->create([...$request->validated(), 'school_id' => $this->currentSchool()->getKey()]);
        $this->record('period.created', $request, $period);

        return redirect()->route('school.periods.index')->with('status', 'Period added.');
    }

    public function edit(Period $period): View
    {
        $this->ensureCurrentSchool($period);

        return view('school.periods.form', ['school' => $this->currentSchool(), 'period' => $period]);
    }

    public function update(PeriodRequest $request, Period $period): RedirectResponse
    {
        $this->ensureCurrentSchool($period);

        $period->update($request->validated());

        if ($period->is_break) {
            // A break has no lessons.
            $period->entries()->delete();
        }

        $this->record('period.updated', $request, $period);

        return redirect()->route('school.periods.index')->with('status', 'Period updated.');
    }

    /**
     * Removes the period and its timetable cells.
     */
    public function destroy(Request $request, Period $period): RedirectResponse
    {
        $this->ensureCurrentSchool($period);

        $period->delete();
        $this->record('period.deleted', $request, $period);

        return back()->with('status', 'Period deleted.');
    }

    private function record(string $event, Request $request, Period $period): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Period::class,
            auditableId: $period->getKey(),
            newValues: [
                'name' => $period->name,
                'starts_at' => $period->starts_at,
                'ends_at' => $period->ends_at,
                'is_break' => $period->is_break,
            ],
        );
    }
}
