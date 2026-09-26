<?php

namespace App\Models;

use App\Enums\SchoolStatus;
use Carbon\CarbonInterface;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'code',
    'email',
    'phone',
    'logo_path',
    'address',
    'timezone',
    'locale',
    'currency',
    'status',
    'trial_ends_at',
    'max_students',
    'max_staff',
    'price_per_student',
    'billing_cycle',
    'settings',
])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    public function isOperational(): bool
    {
        $status = $this->getAttribute('status');
        $trialEndsAt = $this->getAttribute('trial_ends_at');

        if ($status === SchoolStatus::Active) {
            return true;
        }

        return $status === SchoolStatus::Trial
            && ($trialEndsAt === null || ($trialEndsAt instanceof CarbonInterface && $trialEndsAt->isFuture()));
    }

    /**
     * A value from the school's own settings (see School Settings), or $default.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->settings ?? [], $key);

        return $value === null || $value === '' ? $default : $value;
    }

    /**
     * Days the school works, as Carbon day numbers (0 = Sunday … 6 = Saturday).
     * Monday to Saturday unless the school has chosen otherwise.
     *
     * @return array<int, int>
     */
    public function workingDays(): array
    {
        $days = $this->setting('working_days');

        return is_array($days) ? array_values(array_map('intval', $days)) : [1, 2, 3, 4, 5, 6];
    }

    /**
     * Start of generated admission numbers, e.g. "ADM" gives ADM-2026-0001.
     */
    public function admissionPrefix(): string
    {
        return (string) $this->setting('admission_no_prefix', 'ADM');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * School roles only; platform roles have no school.
     *
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Pages the Super Admin has allowed this school to use.
     *
     * @return BelongsToMany<Menu, $this>
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'school_menus')->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_student' => 'decimal:2',
            'settings' => 'array',
            'status' => SchoolStatus::class,
            'trial_ends_at' => 'datetime',
        ];
    }
}
