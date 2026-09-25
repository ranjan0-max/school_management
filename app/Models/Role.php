<?php

namespace App\Models;

use App\Enums\RoleType;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property RoleType $type
 * @property int|null $school_id
 * @property string $name
 * @property string|null $description
 * @property bool $all_school_menus
 * @property bool $is_active
 */
#[Fillable([
    'type',
    'school_id',
    'name',
    'description',
    'all_school_menus',
    'is_active',
])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsToMany<Menu, $this>
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'role_menus')
            ->withPivot(Menu::ACTION_COLUMNS)
            ->withTimestamps();
    }

    public function isPlatform(): bool
    {
        return $this->type === RoleType::Platform;
    }

    /**
     * A user of the given school may hold this role only if it is a platform role
     * or a role that belongs to that same school.
     */
    public function isUsableIn(?int $schoolId): bool
    {
        return $this->isPlatform() || ($schoolId !== null && (int) $this->school_id === $schoolId);
    }

    /**
     * Roles that may be assigned to users of the given school.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeAssignableIn(Builder $query, int $schoolId): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $query) use ($schoolId): void {
                $query->where('type', RoleType::Platform->value)
                    ->orWhere('school_id', $schoolId);
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RoleType::class,
            'all_school_menus' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
