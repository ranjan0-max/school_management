@extends('layouts.dashboard', ['title' => 'Holidays', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php($hasFilters = $search !== '' || $selectedYear > 0)

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ATTENDANCE</span>
            <h1>Holidays</h1>
            <p>Attendance cannot be taken on these days, and attendance reports mark them as holidays.</p>
        </div>
        @can('menu', ['holidays', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.holidays.create') }}">+ Add holiday</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif

    <section class="dashboard-panel attendance-panel mt-4">
        <form class="school-filter-bar attendance-filter-bar attendance-filter-holidays" method="GET" action="{{ route('school.holidays.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search holidays</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Holiday name">
            </div>
            <div>
                <label class="visually-hidden" for="year">Year</label>
                <select id="year" class="form-select app-form-control" name="year">
                    <option value="">All years</option>
                    @foreach ($years as $yearOption)
                        <option value="{{ $yearOption }}" @selected($selectedYear === $yearOption)>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($hasFilters)<a class="btn school-clear-button" href="{{ route('school.holidays.index') }}">Clear</a>@endif
        </form>

        @if ($holidays->isEmpty())
            <div class="school-list-empty">
                <span>H</span>
                <h2>No holidays found</h2>
                <p>{{ $hasFilters ? 'Try changing your filters.' : 'Add festivals and vacations. A holiday can be one day or a range of days.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Holiday</th><th>Dates</th><th class="text-center">Days</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($holidays as $holidayItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(mb_substr($holidayItem->name, 0, 1)) }}</span>
                                        <div><strong>{{ $holidayItem->name }}</strong><small>{{ $holidayItem->description ? \Illuminate\Support\Str::limit($holidayItem->description, 70) : '—' }}</small></div>
                                    </div>
                                </td>
                                <td>
                                    {{ $holidayItem->starts_on->format('D, d M Y') }}
                                    @if ($holidayItem->ends_on)<span class="text-muted"> – {{ $holidayItem->ends_on->format('D, d M Y') }}</span>@endif
                                </td>
                                <td class="text-center"><span class="academic-count-pill">{{ $holidayItem->days() }}</span></td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        @can('menu', ['holidays', 'edit'])
                                            <a class="btn school-row-action" href="{{ route('school.holidays.edit', $holidayItem) }}">Edit</a>
                                        @endcan
                                        @can('menu', ['holidays', 'delete'])
                                            <form method="POST" action="{{ route('school.holidays.destroy', $holidayItem) }}" data-confirm="Delete the holiday {{ $holidayItem->name }}?">
                                                @csrf @method('DELETE')
                                                <button class="btn school-row-action school-row-action-danger" type="submit">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $holidays->links() }}</div>
        @endif
    </section>
@endsection
