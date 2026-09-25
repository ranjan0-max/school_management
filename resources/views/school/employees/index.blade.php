@extends('layouts.dashboard', ['title' => $type->pluralLabel(), 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php($route = $type->routePrefix())

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">PEOPLE MANAGEMENT</span>
            <h1>{{ $type->pluralLabel() }}</h1>
            <p>{{ $type === \App\Enums\EmployeeType::Teacher
                ? 'Teaching staff of the school. Teachers can be assigned in the timetable.'
                : 'Non-teaching staff such as office, accounts, transport and support.' }}</p>
        </div>
        @can('menu', [$type->menuKey(), 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route($route.'.create') }}">+ Add {{ strtolower($type->label()) }}</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route($route.'.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by name, number, phone or email">
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
            @if ($search !== '' || $selectedStatus !== null)<a class="btn school-clear-button" href="{{ route($route.'.index') }}">Clear</a>@endif
        </form>

        @if ($employees->isEmpty())
            <div class="school-list-empty">
                <span>{{ substr($type->pluralLabel(), 0, 1) }}</span>
                <h2>No {{ strtolower($type->pluralLabel()) }} found</h2>
                <p>{{ $search !== '' || $selectedStatus !== null ? 'Try changing your filters.' : 'Add the first '.strtolower($type->label()).'. Only the name is required.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Name</th><th>Number</th><th>Designation</th><th>Phone</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($employees as $employeeItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(substr($employeeItem->name, 0, 1)) }}</span>
                                        <div><strong>{{ $employeeItem->name }}</strong><small>{{ $employeeItem->email ?: ($employeeItem->qualification ?: '—') }}</small></div>
                                    </div>
                                </td>
                                <td><code>{{ $employeeItem->employee_no }}</code></td>
                                <td>{{ $employeeItem->designation ?: '—' }}</td>
                                <td>{{ $employeeItem->phone ?: '—' }}</td>
                                <td><span class="school-status {{ $employeeItem->status->value === 'active' ? 'school-status-active' : 'school-status-cancelled' }}">{{ ucfirst($employeeItem->status->value) }}</span></td>
                                <td class="text-end">
                                    @can('menu', [$type->menuKey(), 'edit'])
                                        <a class="btn school-row-action" href="{{ route($route.'.edit', $employeeItem) }}">Edit</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $employees->links() }}</div>
        @endif
    </section>
@endsection
