<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'school_id',
    'actor_id',
    'event',
    'auditable_type',
    'auditable_id',
    'request_id',
    'ip_address',
    'user_agent',
    'old_values',
    'new_values',
    'metadata',
    'created_at',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit logs are append-only and cannot be updated.'));
        static::deleting(fn (): never => throw new LogicException('Audit logs are append-only and cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
