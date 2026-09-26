<?php

namespace App\Models;

use App\Enums\NoticeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school notice. audience "school" reaches everyone; "selected" reaches the roles and
 * class parents in notice_audiences. Pinned notices stay on top until unpinned or expired.
 *
 * @property int $school_id
 * @property string $title
 * @property string|null $body
 * @property string $audience
 * @property NoticeStatus $status
 * @property bool $is_pinned
 * @property CarbonImmutable|null $publish_at
 * @property CarbonImmutable|null $expires_on
 * @property int|null $created_by
 */
#[Fillable([
    'school_id',
    'title',
    'body',
    'audience',
    'status',
    'is_pinned',
    'publish_at',
    'expires_on',
    'created_by',
    'archived_by',
    'archived_at',
])]
class Notice extends Model
{
    public const AUDIENCE_SCHOOL = 'school';

    public const AUDIENCE_SELECTED = 'selected';

    public const TARGET_ROLE = 'role';

    public const TARGET_CLASS = 'class';

    /**
     * @return HasMany<NoticeAudience, $this>
     */
    public function audiences(): HasMany
    {
        return $this->hasMany(NoticeAudience::class);
    }

    /**
     * @return HasMany<NoticeRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Live notices this user should see: published, publish time reached, not expired,
     * and addressed to the whole school or to the user's role. $today is the school's date.
     *
     * @param  Builder<Notice>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, string $today): void
    {
        $query->where('status', NoticeStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', $today))
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('audience', self::AUDIENCE_SCHOOL)
                ->orWhereHas('audiences', fn (Builder $query) => $query
                    ->where('target_type', self::TARGET_ROLE)
                    ->where('target_id', (int) $user->role_id))));
    }

    /**
     * Pinned first, then newest.
     *
     * @param  Builder<Notice>  $query
     */
    public function scopeBoardOrder(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByRaw('COALESCE(publish_at, created_at) DESC')->orderByDesc('id');
    }

    /**
     * What a manager sees: draft, scheduled, live, expired or archived.
     */
    public function state(string $today): string
    {
        return match (true) {
            $this->status === NoticeStatus::Archived => 'archived',
            $this->status === NoticeStatus::Draft => 'draft',
            $this->publish_at !== null && $this->publish_at->isFuture() => 'scheduled',
            $this->expires_on !== null && $this->expires_on->toDateString() < $today => 'expired',
            default => 'live',
        };
    }

    /**
     * @return array<int, int>
     */
    public function targetIds(string $type): array
    {
        return $this->audiences->where('target_type', $type)->pluck('target_id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NoticeStatus::class,
            'is_pinned' => 'boolean',
            'publish_at' => 'datetime',
            'expires_on' => 'date',
            'archived_at' => 'datetime',
        ];
    }
}
