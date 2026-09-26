@extends('layouts.dashboard', ['title' => 'Users', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php
        $hasFilters = $search !== '' || $selectedRoleId !== null || $selectedStatus !== null;
        $canEdit = auth()->user()?->can('menu', ['users', 'edit']) ?? false;
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ADMINISTRATION</span>
            <h1>Users</h1>
            <p>People who can sign in to {{ $school->name }}. Users are never deleted: make them inactive to stop their access.</p>
        </div>
        @can('menu', ['users', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.users.create') }}">+ Add user</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.users.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search users</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Name, email or phone">
            </div>
            <div>
                <label class="visually-hidden" for="role_id">Role</label>
                <select id="role_id" class="form-select app-form-control" name="role_id">
                    <option value="">All roles</option>
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->id }}" @selected($selectedRoleId === $roleOption->id)>{{ $roleOption->name }}{{ $roleOption->is_active ? '' : ' (inactive)' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="status">Status</label>
                <select id="status" class="form-select app-form-control" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected($selectedStatus === $statusOption)>{{ ucfirst($statusOption->value) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($hasFilters)<a class="btn school-clear-button" href="{{ route('school.users.index') }}">Clear</a>@endif
        </form>

        @if ($users->isEmpty())
            <div class="school-list-empty">
                <span>U</span>
                <h2>No users found</h2>
                <p>{{ $hasFilters ? 'Try changing your filters.' : 'Add the first user and give them a role.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last sign in</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($users as $userItem)
                            @php($locked = $userItem->is(auth()->user()) || $userItem->role?->isPlatform())
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(mb_substr($userItem->name ?: $userItem->email, 0, 1)) }}</span>
                                        <div>
                                            <strong>{{ $userItem->name ?: '—' }}{{ $userItem->is(auth()->user()) ? ' (you)' : '' }}</strong>
                                            <small>{{ $userItem->email }}{{ $userItem->phone ? ' · '.$userItem->phone : '' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($userItem->role)
                                        {{ $userItem->role->name }}
                                        @if ($userItem->role->isPlatform())<small class="d-block text-muted">Set by platform</small>@elseif (! $userItem->role->is_active)<small class="d-block text-muted">Role inactive</small>@endif
                                    @else
                                        <span class="text-muted">No role</span>
                                    @endif
                                </td>
                                <td><span class="school-status {{ $userItem->status->value === 'active' ? 'school-status-active' : 'school-status-cancelled' }}">{{ ucfirst($userItem->status->value) }}</span></td>
                                <td>{{ $userItem->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}</td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        @if ($canEdit)
                                            <a class="btn school-row-action" href="{{ route('school.users.edit', $userItem) }}">{{ $userItem->role?->isPlatform() ? 'View' : 'Edit' }}</a>
                                            @unless ($locked)
                                                <form method="POST" action="{{ route('school.users.status', $userItem) }}" data-confirm="{{ $userItem->status->value === 'active' ? 'Make '.$userItem->name.' inactive? They will not be able to sign in.' : 'Allow '.$userItem->name.' to sign in again?' }}">
                                                    @csrf @method('PATCH')
                                                    <button class="btn school-row-action {{ $userItem->status->value === 'active' ? 'school-row-action-danger' : '' }}" type="submit">{{ $userItem->status->value === 'active' ? 'Deactivate' : 'Activate' }}</button>
                                                </form>
                                            @endunless
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $users->links() }}</div>
        @endif
    </section>
@endsection
