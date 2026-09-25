<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int|null $school_id
 * @property int|null $role_id
 * @property UserStatus $status
 */
#[Fillable([
    'school_id',
    'role_id',
    'name',
    'email',
    'phone',
    'password',
    'is_super_admin',
    'status',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Per-user exceptions on top of the role: NULL follows the role, true grants, false denies.
     *
     * @return BelongsToMany<Menu, $this>
     */
    public function menuOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'user_menu_overrides')
            ->withPivot(Menu::ACTION_COLUMNS)
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }
}
