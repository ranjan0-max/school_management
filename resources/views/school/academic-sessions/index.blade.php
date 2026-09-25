@extends('layouts.dashboard', ['title' => 'Academic Sessions', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ACADEMIC MANAGEMENT</span>
            <h1>Academic sessions</h1>
            <p>School years such as 2026-27. One session is marked current and used as the default everywhere.</p>
        </div>
        @can('menu', ['academic_sessions', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.academic-sessions.create') }}">+ Add session</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert school-access-error mt-4 mb-0" role="alert"><span>{{ session('error') }}</span></div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.academic-sessions.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search sessions</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by session name">
            </div>
            <button class="btn school-filter-button" type="submit">Search</button>
            @if ($search !== '')<a class="btn school-clear-button" href="{{ route('school.academic-sessions.index') }}">Clear</a>@endif
        </form>

        @if ($sessions->isEmpty())
            <div class="school-list-empty">
                <span>S</span>
                <h2>No academic sessions</h2>
                <p>{{ $search !== '' ? 'No session matches your search.' : 'Add the first session, for example 2026-27, and mark it as current.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Session</th><th>Starts</th><th>Ends</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($sessions as $sessionItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(substr($sessionItem->name, 0, 1)) }}</span>
                                        <div>
                                            <strong>{{ $sessionItem->name }} @if ($sessionItem->is_current)<span class="academic-current-badge">Current</span>@endif</strong>
                                            <small>{{ $sessionItem->starts_on->diffInMonths($sessionItem->ends_on) }} months</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $sessionItem->starts_on->format('d M Y') }}</td>
                                <td>{{ $sessionItem->ends_on->format('d M Y') }}</td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        @can('menu', ['academic_sessions', 'edit'])
                                            @unless ($sessionItem->is_current)
                                                <form method="POST" action="{{ route('school.academic-sessions.current', $sessionItem) }}">
                                                    @csrf @method('PATCH')
                                                    <button class="btn school-row-action" type="submit">Make current</button>
                                                </form>
                                            @endunless
                                            <a class="btn school-row-action" href="{{ route('school.academic-sessions.edit', $sessionItem) }}">Edit</a>
                                        @endcan
                                        @can('menu', ['academic_sessions', 'delete'])
                                            @unless ($sessionItem->is_current)
                                                <form method="POST" action="{{ route('school.academic-sessions.destroy', $sessionItem) }}" data-confirm="Delete session {{ $sessionItem->name }}?">
                                                    @csrf @method('DELETE')
                                                    <button class="btn school-row-action school-row-action-danger" type="submit">Delete</button>
                                                </form>
                                            @endunless
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $sessions->links() }}</div>
        @endif
    </section>
@endsection
