@extends('layouts.dashboard', ['title' => 'Roles & Access', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php($hasFilters = $search !== '' || $selectedState !== '')

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ADMINISTRATION</span>
            <h1>Roles &amp; Access</h1>
            <p>Roles of {{ $school->name }}, such as Teacher or Accountant. Tick what each role can do; turn a role off instead of deleting it.</p>
        </div>
        @can('menu', ['roles', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.roles.create') }}">+ Add role</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.roles.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search roles</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Role name">
            </div>
            <div>
                <label class="visually-hidden" for="state">Status</label>
                <select id="state" class="form-select app-form-control" name="state">
                    <option value="">All roles</option>
                    <option value="active" @selected($selectedState === 'active')>Active</option>
                    <option value="inactive" @selected($selectedState === 'inactive')>Inactive</option>
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($hasFilters)<a class="btn school-clear-button" href="{{ route('school.roles.index') }}">Clear</a>@endif
        </form>

        @if ($roles->isEmpty())
            <div class="school-list-empty">
                <span>R</span>
                <h2>No roles found</h2>
                <p>{{ $hasFilters ? 'Try changing your filters.' : 'Create roles such as Teacher, Accountant or Receptionist, then give them to users.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Role</th><th>Access</th><th class="text-center">Users</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($roles as $roleItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(mb_substr($roleItem->name, 0, 1)) }}</span>
                                        <div>
                                            <strong>{{ $roleItem->name }}{{ (int) $ownRoleId === $roleItem->id ? ' (your role)' : '' }}</strong>
                                            <small>{{ $roleItem->description ? \Illuminate\Support\Str::limit($roleItem->description, 70) : '—' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $roleItem->all_school_menus ? 'All school menus' : $roleItem->menus_count.' page(s)' }}</td>
                                <td class="text-center"><span class="academic-count-pill">{{ $roleItem->users_count }}</span></td>
                                <td><span class="school-status {{ $roleItem->is_active ? 'school-status-active' : 'school-status-cancelled' }}">{{ $roleItem->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end">
                                    @can('menu', ['roles', 'edit'])
                                        <a class="btn school-row-action" href="{{ route('school.roles.edit', $roleItem) }}">{{ (int) $ownRoleId === $roleItem->id ? 'View' : 'Edit' }}</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $roles->links() }}</div>
        @endif
    </section>
@endsection
