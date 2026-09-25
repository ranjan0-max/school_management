<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        string $event,
        ?User $actor = null,
        ?School $school = null,
        ?string $auditableType = null,
        int|string|null $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): ?AuditLog {
        if (! Schema::hasTable('audit_logs')) {
            Log::notice('Database audit table unavailable; event written to application log.', [
                'event' => $event,
                'school_id' => $school?->getKey(),
                'actor_id' => $actor?->getKey(),
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'metadata' => $metadata,
            ]);

            return null;
        }

        return AuditLog::query()->create([
            'school_id' => $school?->getKey(),
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'request_id' => $this->requestId(),
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 1024),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * The header is client-controlled; only a valid UUID may reach the uuid column.
     */
    private function requestId(): ?string
    {
        $requestId = $this->request->headers->get('X-Request-ID');

        return is_string($requestId) && Str::isUuid($requestId) ? $requestId : null;
    }
}
