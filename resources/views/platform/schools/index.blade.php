@extends('layouts.dashboard', ['title' => 'Schools', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">PLATFORM DIRECTORY</span>
            <h1>Schools</h1>
            <p>Create and manage every tenant workspace from one secure place.</p>
        </div>
        <a class="btn btn-primary app-btn-primary" href="{{ route('platform.schools.create') }}">+ Create school</a>
    </div>

    @if (session('status'))
        <div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>
    @endif

    <div class="row g-4 mt-1">
        <div class="col-md-6"><article class="dashboard-metric-card school-compact-metric"><span class="dashboard-metric-icon purple">S</span><small>Total schools</small><strong>{{ number_format($schoolCount) }}</strong></article></div>
        <div class="col-md-6"><article class="dashboard-metric-card school-compact-metric"><span class="dashboard-metric-icon green">O</span><small>Operational or on trial</small><strong>{{ number_format($operationalCount) }}</strong></article></div>
    </div>

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('platform.schools.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search schools</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by name, code or email">
            </div>
            <div>
                <label class="visually-hidden" for="status">Filter by status</label>
                <select id="status" class="form-select app-form-control" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($search !== '' || $selectedStatus !== '')
                <a class="btn school-clear-button" href="{{ route('platform.schools.index') }}">Clear</a>
            @endif
        </form>

        @if ($schools->isEmpty())
            <div class="school-list-empty">
                <span>S</span>
                <h2>No schools found</h2>
                <p>{{ $search !== '' || $selectedStatus !== '' ? 'Try changing your search filters.' : 'Create the first school to start assigning modules and users.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>School</th><th>Status</th><th>Users</th><th>Created</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($schools as $schoolItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(substr($schoolItem->name, 0, 1)) }}</span>
                                        <div><strong>{{ $schoolItem->name }}</strong><small>{{ $schoolItem->code ?: ($schoolItem->email ?: 'Details pending') }}</small></div>
                                    </div>
                                </td>
                                <td><span class="school-status school-status-{{ $schoolItem->status->value }}">{{ ucfirst($schoolItem->status->value) }}</span></td>
                                <td>{{ number_format($schoolItem->users_count) }}</td>
                                <td>{{ $schoolItem->created_at?->format('d M Y') }}</td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        <a class="btn school-row-action school-row-access" href="{{ route('platform.schools.access', $schoolItem) }}">Access</a>
                                        <a class="btn school-row-action" href="{{ route('platform.schools.edit', $schoolItem) }}">Manage</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $schools->links() }}</div>
        @endif
    </section>
@endsection
