<?php

namespace App\Models;

use App\Enums\MenuAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $parent_id
 * @property string $key
 * @property string $name
 * @property string|null $actions
 * @property bool $is_active
 */
#[Fillable([
    'parent_id',
    'key',
    'name',
    'route_name',
    'icon',
    'actions',
    'sort_order',
    'is_active',
])]
class Menu extends Model
{
    public const ACTION_COLUMNS = ['can_view', 'can_create', 'can_edit', 'can_delete', 'can_export', 'can_approve'];

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Assignable pages: active menus that sit under a group.
     *
     * @param  Builder<Menu>  $query
     */
    public function scopePages(Builder $query): void
    {
        $query->whereNotNull('parent_id')->where('is_active', true);
    }

    /**
     * Active groups with their active pages, optionally limited to the given page ids
     * (for example a school's assigned pages). Groups left without pages are dropped.
     *
     * @param  array<int, int>|null  $pageIds
     * @return Collection<int, Menu>
     */
    public static function catalog(?array $pageIds = null): Collection
    {
        return self::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with('children')
            ->orderBy('sort_order')
            ->get()
            ->each(fn (Menu $group) => $group->setRelation('children', $group->children
                ->filter(fn (Menu $page): bool => $page->is_active && ($pageIds === null || in_array((int) $page->getKey(), $pageIds, true)))
                ->values()))
            ->filter(fn (Menu $group): bool => $group->children->isNotEmpty())
            ->values();
    }

    /**
     * Actions that make sense for this page, for example view + export on a report.
     *
     * @return array<int, MenuAction>
     */
    public function availableActions(): array
    {
        return self::parseActions($this->actions);
    }

    /**
     * @return array<int, MenuAction>
     */
    public static function parseActions(?string $actions): array
    {
        $parsed = [];

        foreach (explode(',', (string) $actions) as $action) {
            $case = MenuAction::tryFrom(trim($action));

            if ($case !== null) {
                $parsed[] = $case;
            }
        }

        return $parsed;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
