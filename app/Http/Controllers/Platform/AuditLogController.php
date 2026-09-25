<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(): View
    {
        $auditReady = Schema::hasTable('audit_logs');
        $search = mb_substr(trim(request()->string('search')->toString()), 0, 100);

        if (! $auditReady) {
            return view('platform.audit-logs.index', [
                'auditReady' => false,
                'auditLogs' => new LengthAwarePaginator([], 0, 25),
                'search' => $search,
            ]);
        }

        $auditLogs = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
            ->leftJoin('schools', 'schools.id', '=', 'audit_logs.school_id')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('audit_logs.event', 'like', "%{$search}%")
                        ->orWhere('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('schools.name', 'like', "%{$search}%");
                });
            })
            ->select([
                'audit_logs.id',
                'audit_logs.event',
                'audit_logs.auditable_type',
                'audit_logs.auditable_id',
                'audit_logs.ip_address',
                'audit_logs.metadata',
                'audit_logs.created_at',
                'users.name as actor_name',
                'users.email as actor_email',
                'schools.name as school_name',
            ])
            ->latest('audit_logs.id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.audit-logs.index', [
            'auditReady' => true,
            'auditLogs' => $auditLogs,
            'search' => $search,
        ]);
    }
}
