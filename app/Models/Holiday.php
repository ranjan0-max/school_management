<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $school_id
 * @property string $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string|null $description
 */
#[Fillable([
    'school_id',
    'name',
    'starts_on',
    'ends_on',
    'description',
])]
class Holiday extends Model
{
    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The school's holiday on this day, if any.
     */
    public static function coveringDay(int $schoolId, string $date): ?self
    {
        return self::query()->where('school_id', $schoolId)->overlapping($date, $date)->orderBy('starts_on')->first();
    }

    /**
     * Holidays that touch the period from $from to $to (Y-m-d, inclusive).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->whereDate('starts_on', '<=', $to)
            ->where(fn (Builder $query) => $query
                ->whereDate('ends_on', '>=', $from)
                ->orWhere(fn (Builder $query) => $query->whereNull('ends_on')->whereDate('starts_on', '>=', $from)));
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->ends_on ?? $this->starts_on;
    }

    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->lastDay()) + 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
