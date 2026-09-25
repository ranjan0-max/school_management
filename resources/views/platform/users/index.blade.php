@extends('layouts.dashboard', ['title' => 'Users', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">USERS</span>
            <h1>School users</h1>
            <p>Every school user, their school, role and status.</p>
        </div>
        <a class="btn btn-primary app-btn-primary" href="{{ route('platform.users.create', ['school_id' => $selectedSchoolId]) }}">+ Add user</a>
    </div>

    @if (session('status'))
        <div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>
    @endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('platform.users.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search users</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by name, email or phone">
            </div>
            <div>
                <label class="visually-hidden" for="school_id">School</label>
                <select id="school_id" class="form-select app-form-control" name="school_id">
                    <option value="">All schools</option>
                    @foreach ($schools as $schoolOption)
                        <option value="{{ $schoolOption->id }}" @selected($selectedSchoolId === $schoolOption->id)>{{ $schoolOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="status">Status</label>
                <select id="status" class="form-select app-form-control" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($selectedStatus === $status)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($search !== '' || $selectedSchoolId !== null || $selectedStatus !== null)
                <a class="btn school-clear-button" href="{{ route('platform.users.index') }}">Clear</a>
            @endif
        </form>

        @if ($users->isEmpty())
            <div class="school-list-empty">
                <span>U</span>
                <h2>No users found</h2>
                <p>{{ $search !== '' || $selectedSchoolId !== null || $selectedStatus !== null ? 'Try changing your filters.' : 'Add the first school user, for example the principal.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>User</th><th>School</th><th>Role</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($users as $userItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(substr($userItem->name ?: $userItem->email, 0, 1)) }}</span>
                                        <div><strong>{{ $userItem->name ?: 'Name pending' }}</strong><small>{{ $userItem->email }}</small></div>
                                    </div>
                                </td>
                                <td>{{ $userItem->school?->name ?? '—' }}</td>
                                <td>
                                    @if ($userItem->role)
                                        {{ $userItem->role->name }}
                                        @if ($userItem->role->isPlatform())<span class="user-role-badge">Platform</span>@endif
                                    @else
                                        <span class="text-muted">No role</span>
                                    @endif
                                </td>
                                <td><span class="school-status {{ $userItem->status->value === 'active' ? 'school-status-active' : 'school-status-cancelled' }}">{{ ucfirst($userItem->status->value) }}</span></td>
                                <td class="text-end"><a class="btn school-row-action" href="{{ route('platform.users.edit', $userItem) }}">Manage</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $users->links() }}</div>
        @endif
    </section>
@endsection
