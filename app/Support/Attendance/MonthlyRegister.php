<?php

namespace App\Support\Attendance;

use App\Models\Holiday;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One month of attendance laid out as a register: a column per day, a row per person.
 *
 * Attendance % = (present + late + ½ × half day) ÷ days marked for that person.
 */
final class MonthlyRegister
{
    /**
     * @param  array<int, string>  $holidays  day of month => holiday name
     * @param  array<int, true>  $takenDays  days of month on which attendance was taken
     * @param  array<int, int>  $workingDays  weekdays the school works (0 = Sunday)
     */
    private function __construct(
        public readonly CarbonImmutable $month,
        public readonly array $holidays,
        public readonly array $takenDays,
        public readonly array $workingDays,
    ) {}

    /**
     * @param  Builder<Model>  $sheets  the sheets that count for this register (already scoped)
     */
    public static function for(School $school, CarbonImmutable $month, Builder $sheets): self
    {
        $schoolId = (int) $school->getKey();
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->startOfDay();
        $holidays = [];

        foreach (Holiday::query()->where('school_id', $schoolId)->overlapping($from->toDateString(), $to->toDateString())->orderBy('starts_on')->get() as $holiday) {
            $day = CarbonImmutable::parse($holiday->starts_on->toDateString())->max($from);
            $last = CarbonImmutable::parse($holiday->lastDay()->toDateString())->min($to);

            for (; $day->lte($last); $day = $day->addDay()) {
                $holidays[$day->day] ??= $holiday->name;
            }
        }

        $takenDays = [];

        foreach ((clone $sheets)->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->pluck('date') as $date) {
            $takenDays[CarbonImmutable::parse($date)->day] = true;
        }

        return new self($from, $holidays, $takenDays, $school->workingDays());
    }

    /**
     * A weekly off day, such as Sunday, from the school's working days setting.
     */
    public function isOffDay(CarbonImmutable $day): bool
    {
        return ! in_array($day->dayOfWeek, $this->workingDays, true);
    }

    /**
     * Parses ?month=YYYY-MM, falling back to $default.
     */
    public static function parseMonth(string $value, CarbonImmutable $default): CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $parts) === 1 && (int) $parts[2] >= 1 && (int) $parts[2] <= 12) {
            return CarbonImmutable::create((int) $parts[1], (int) $parts[2], 1);
        }

        return $default->startOfMonth();
    }

    /**
     * @return array<int, CarbonImmutable> day of month => date
     */
    public function days(): array
    {
        $days = [];

        for ($day = $this->month; $day->month === $this->month->month; $day = $day->addDay()) {
            $days[$day->day] = $day;
        }

        return $days;
    }

    public function from(): string
    {
        return $this->month->toDateString();
    }

    public function to(): string
    {
        return $this->month->endOfMonth()->toDateString();
    }

    /**
     * @param  iterable<Model>  $records  attendance rows with `date` and `status` casts
     * @return array<int, PersonMonth> person id => their month
     */
    public function summarise(iterable $records, string $personKey): array
    {
        $people = [];

        foreach ($records as $record) {
            $people[(int) $record->getAttribute($personKey)] ??= new PersonMonth;
            $people[(int) $record->getAttribute($personKey)]->add($record->getAttribute('date')->day, $record->getAttribute('status'));
        }

        return $people;
    }

    /**
     * The register row for someone with no records yet.
     */
    public function empty(): PersonMonth
    {
        return new PersonMonth;
    }
}
