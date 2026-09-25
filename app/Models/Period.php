<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $school_id
 * @property string $name
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property bool $is_break
 * @property int $sort_order
 */
#[Fillable([
    'school_id',
    'name',
    'starts_at',
    'ends_at',
    'is_break',
    'sort_order',
])]
class Period extends Model
{
    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<TimetableEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * "09:00 – 09:40", or an empty string when times are not set.
     */
    public function timeRange(): string
    {
        $start = $this->starts_at === null ? null : substr($this->starts_at, 0, 5);
        $end = $this->ends_at === null ? null : substr($this->ends_at, 0, 5);

        return trim(($start ?? '').($start !== null && $end !== null ? ' – ' : '').($end ?? ''));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
