<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Activity inside the current school only: who did what, and when.
 */
class AuditLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('menu:audit_logs', only: ['index']),
            new Middleware('menu:audit_logs,export', only: ['export']),
        ];
    }

    public function index(Request $request): View
    {
        [$search, $from, $to] = $this->filters($request);

        return view('school.admin.audit-logs.index', [
            'school' => $this->currentSchool(),
            'auditLogs' => $this->query($search, $from, $to)->paginate(25)->withQueryString(),
            'search' => $search,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$search, $from, $to] = $this->filters($request);
        $query = $this->query($search, $from, $to);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Time', 'Event', 'Done by', 'Email', 'Record', 'IP address']);

            foreach ($query->cursor() as $log) {
                fputcsv($out, array_map(fn ($value): string => $this->safeCell((string) $value), [
                    CarbonImmutable::parse($log->created_at)->format('Y-m-d H:i'),
                    $log->event,
                    $log->actor_name ?? 'System',
                    $log->actor_email,
                    $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '',
                    $log->ip_address,
                ]));
            }

            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function filters(Request $request): array
    {
        $date = fn (string $value): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;

        return [
            mb_substr(trim($request->string('search')->toString()), 0, 100),
            $date($request->string('from')->toString()),
            $date($request->string('to')->toString()),
        ];
    }

    private function query(string $search, ?string $from, ?string $to): Builder
    {
        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
            ->where('audit_logs.school_id', $this->currentSchool()->getKey())
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('audit_logs.event', 'like', "%{$search}%")
                ->orWhere('users.name', 'like', "%{$search}%")
                ->orWhere('users.email', 'like', "%{$search}%")))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('audit_logs.created_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('audit_logs.created_at', '<=', $to))
            ->select([
                'audit_logs.id',
                'audit_logs.event',
                'audit_logs.auditable_type',
                'audit_logs.auditable_id',
                'audit_logs.ip_address',
                'audit_logs.created_at',
                'users.name as actor_name',
                'users.email as actor_email',
            ])
            ->orderByDesc('audit_logs.id');
    }

    /**
     * Stops spreadsheet apps from running a value like "=cmd" as a formula.
     */
    private function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
