@extends('layouts.dashboard', ['title' => 'Attendance Reports', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php
        $filters = array_filter(['month' => $month->format('Y-m'), 'type' => $selectedType?->value, 'search' => $search]);
        $hasFilters = $search !== '' || $selectedType !== null;
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ATTENDANCE</span>
            <h1>Attendance Reports</h1>
            <p>Monthly register of teachers and staff: day-wise marks, totals and attendance percentage.</p>
        </div>
        @can('menu', ['attendance_reports', 'export'])
            <a class="btn school-secondary-button" href="{{ route('school.attendance-reports.staff-export', $filters) }}">Export CSV</a>
        @endcan
    </div>

    @include('school.attendance-reports._tabs')

    <section class="dashboard-panel attendance-panel mt-3">
        <form class="school-filter-bar attendance-filter-bar attendance-filter-staff" method="GET" action="{{ route('school.attendance-reports.staff') }}">
            <div>
                <label class="visually-hidden" for="month">Month</label>
                <input id="month" class="form-control app-form-control" name="month" type="month" value="{{ $month->format('Y-m') }}">
            </div>
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search staff</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Name or employee no">
            </div>
            <div>
                <label class="visually-hidden" for="type">Type</label>
                <select id="type" class="form-select app-form-control" name="type">
                    <option value="">Teachers &amp; staff</option>
                    @foreach ($types as $typeOption)
                        <option value="{{ $typeOption->value }}" @selected($selectedType === $typeOption)>{{ $typeOption->pluralLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Show report</button>
            @if ($hasFilters)<a class="btn school-clear-button" href="{{ route('school.attendance-reports.staff', ['month' => $month->format('Y-m')]) }}">Clear</a>@endif
        </form>

        @if ($employees->isEmpty())
            <div class="school-list-empty">
                <span>R</span>
                <h2>Nobody found</h2>
                <p>{{ $hasFilters ? 'Try changing your filters.' : 'Add teachers and staff first.' }}</p>
            </div>
        @else
            <div class="attendance-day-title">
                <h2>{{ $month->format('F Y') }}</h2>
                <span>{{ $employees->total() }} people</span>
            </div>

            @include('school.attendance-reports._register', [
                'leadHeading' => 'Name',
                'people' => $employees->getCollection()->map(fn ($employee) => [
                    'id' => $employee->id,
                    'lead' => null,
                    'name' => $employee->name,
                    'meta' => $employee->employee_no.' · '.($employee->designation ?: $employee->type->label()).($employee->status->value !== 'active' ? ' · Left' : ''),
                ]),
            ])
            <div class="school-pagination">{{ $employees->links() }}</div>
        @endif
    </section>
@endsection
