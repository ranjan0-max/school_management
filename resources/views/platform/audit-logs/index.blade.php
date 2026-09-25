@extends('layouts.dashboard', ['title' => 'Audit Logs', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">SECURITY &amp; ACCOUNTABILITY</span>
            <h1>Audit logs</h1>
            <p>Review sensitive platform and school access changes in chronological order.</p>
        </div>
    </div>

    @if (! $auditReady)
        <section class="dashboard-panel audit-not-ready mt-4">
            <span>A</span>
            <div>
                <h2>Database audit trail is unavailable</h2>
                <p>The audit table could not be found. Events are safely falling back to the daily application log until the table is restored.</p>
            </div>
        </section>
    @else
        <section class="dashboard-panel mt-4">
            <form class="school-filter-bar" method="GET" action="{{ route('platform.audit-logs.index') }}">
                <div class="school-search-field">
                    <label class="visually-hidden" for="search">Search audit logs</label>
                    <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search event, actor, email or school">
                </div>
                <button class="btn school-filter-button" type="submit">Search</button>
                @if ($search !== '')<a class="btn school-clear-button" href="{{ route('platform.audit-logs.index') }}">Clear</a>@endif
            </form>

            @if ($auditLogs->isEmpty())
                <div class="school-list-empty"><span>A</span><h2>No audit events found</h2><p>Sensitive actions will appear here as they occur.</p></div>
            @else
                <div class="table-responsive school-table-wrap">
                    <table class="table school-table align-middle mb-0">
                        <thead><tr><th>Event</th><th>Actor</th><th>School</th><th>IP address</th><th>Time</th></tr></thead>
                        <tbody>
                            @foreach ($auditLogs as $audit)
                                <tr>
                                    <td><span class="audit-event-name">{{ str_replace(['.', '_'], ' ', ucfirst($audit->event)) }}</span></td>
                                    <td><div class="audit-actor"><strong>{{ $audit->actor_name ?: 'System' }}</strong><small>{{ $audit->actor_email }}</small></div></td>
                                    <td>{{ $audit->school_name ?: 'Platform' }}</td>
                                    <td><code class="audit-ip">{{ $audit->ip_address ?: '—' }}</code></td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('d M Y, h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="school-pagination">{{ $auditLogs->links() }}</div>
            @endif
        </section>
    @endif
@endsection
