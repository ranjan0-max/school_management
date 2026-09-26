@extends('layouts.dashboard', ['title' => 'Audit Logs', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php
        $hasFilters = $search !== '' || $from !== null || $to !== null;
        $filters = array_filter(['search' => $search, 'from' => $from, 'to' => $to]);
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ADMINISTRATION</span>
            <h1>Audit Logs</h1>
            <p>Who did what in {{ $school->name }}, newest first.</p>
        </div>
        @can('menu', ['audit_logs', 'export'])
            <a class="btn school-secondary-button" href="{{ route('school.audit-logs.export', $filters) }}">Export CSV</a>
        @endcan
    </div>

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.audit-logs.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search audit logs</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Event, name or email">
            </div>
            <div>
                <label class="visually-hidden" for="from">From</label>
                <input id="from" class="form-control app-form-control" name="from" type="date" value="{{ $from }}" title="From">
            </div>
            <div>
                <label class="visually-hidden" for="to">To</label>
                <input id="to" class="form-control app-form-control" name="to" type="date" value="{{ $to }}" title="To">
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($hasFilters)<a class="btn school-clear-button" href="{{ route('school.audit-logs.index') }}">Clear</a>@endif
        </form>

        @if ($auditLogs->isEmpty())
            <div class="school-list-empty"><span>A</span><h2>No activity found</h2><p>{{ $hasFilters ? 'Try changing your filters.' : 'Changes made in this school will appear here.' }}</p></div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Event</th><th>Done by</th><th>Record</th><th>IP address</th><th>Time</th></tr></thead>
                    <tbody>
                        @foreach ($auditLogs as $audit)
                            <tr>
                                <td><span class="audit-event-name">{{ str_replace(['.', '_'], ' ', ucfirst($audit->event)) }}</span></td>
                                <td><div class="audit-actor"><strong>{{ $audit->actor_name ?: 'System' }}</strong><small>{{ $audit->actor_email }}</small></div></td>
                                <td>{{ $audit->auditable_type ? \Illuminate\Support\Str::headline(class_basename($audit->auditable_type)).' #'.$audit->auditable_id : '—' }}</td>
                                <td><code class="audit-ip">{{ $audit->ip_address ?: '—' }}</code></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($audit->created_at)->timezone($school->timezone ?: config('app.timezone'))->format('d M Y, h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $auditLogs->links() }}</div>
        @endif
    </section>
@endsection
