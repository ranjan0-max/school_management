<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\HolidayRequest;
use App\Models\Holiday;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * School holidays. Attendance cannot be taken on these days and reports skip them.
 */
class HolidayController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:holidays', only: ['index']),
            new Middleware('menu:holidays,create', only: ['create', 'store']),
            new Middleware('menu:holidays,edit', only: ['edit', 'update']),
            new Middleware('menu:holidays,delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $schoolId = $this->currentSchool()->getKey();
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $year = $request->integer('year');

        $thisYear = (int) now()->format('Y');
        $first = Holiday::query()->where('school_id', $schoolId)->min('starts_on');
        $last = Holiday::query()->where('school_id', $schoolId)->max('starts_on');
        $years = collect(range(
            max($thisYear + 1, $last === null ? 0 : (int) substr((string) $last, 0, 4)),
            min($thisYear - 1, $first === null ? PHP_INT_MAX : (int) substr((string) $first, 0, 4)),
        ));

        if (! $years->contains($year)) {
            $year = 0;
        }

        $holidays = Holiday::query()
            ->where('school_id', $schoolId)
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when($year > 0, fn (Builder $query) => $query->overlapping("{$year}-01-01", "{$year}-12-31"))
            ->orderByDesc('starts_on')
            ->paginate(15)
            ->withQueryString();

        return view('school.holidays.index', [
            'school' => $this->currentSchool(),
            'holidays' => $holidays,
            'search' => $search,
            'years' => $years,
            'selectedYear' => $year,
        ]);
    }

    public function create(): View
    {
        return view('school.holidays.form', ['school' => $this->currentSchool(), 'holiday' => new Holiday]);
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        $holiday = Holiday::query()->create([...$request->holidayData(), 'school_id' => $this->currentSchool()->getKey()]);
        $this->record('holiday.created', $request, $holiday);

        return redirect()->route('school.holidays.index')->with('status', "Holiday {$holiday->name} added.");
    }

    public function edit(Holiday $holiday): View
    {
        $this->ensureCurrentSchool($holiday);

        return view('school.holidays.form', ['school' => $this->currentSchool(), 'holiday' => $holiday]);
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $this->ensureCurrentSchool($holiday);

        $holiday->update($request->holidayData());
        $this->record('holiday.updated', $request, $holiday);

        return redirect()->route('school.holidays.index')->with('status', "Holiday {$holiday->name} updated.");
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->ensureCurrentSchool($holiday);

        $holiday->delete();
        $this->record('holiday.deleted', $request, $holiday);

        return back()->with('status', 'Holiday deleted.');
    }

    private function record(string $event, Request $request, Holiday $holiday): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Holiday::class,
            auditableId: $holiday->getKey(),
            newValues: [
                'name' => $holiday->name,
                'starts_on' => $holiday->starts_on->toDateString(),
                'ends_on' => $holiday->ends_on?->toDateString(),
            ],
        );
    }
}
