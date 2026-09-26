@extends('layouts.dashboard', ['title' => 'Staff Attendance', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php
        $user = auth()->user();
        $canSave = $sheet === null ? $user?->can('menu', ['staff_attendance', 'create']) : $user?->can('menu', ['staff_attendance', 'edit']);
        $editable = $holiday === null && ! $sheet?->isApproved() && $canSave;
        $hasFilters = $search !== '' || $selectedType !== null;
        $oldRows = old('attendance');
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ATTENDANCE</span>
            <h1>Staff Attendance</h1>
            <p>Teachers and staff for one day. Check-in and check-out times are optional.</p>
        </div>
        <div class="school-heading-actions">
            @can('menu', ['attendance_reports', 'view'])
                <a class="btn school-secondary-button" href="{{ route('school.attendance-reports.staff', ['month' => $date->format('Y-m')]) }}">Monthly report</a>
            @endcan
        </div>
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert">
            <strong>Attendance was not saved.</strong>
            @foreach (array_unique($errors->all()) as $error)<span>{{ $error }}</span>@endforeach
        </div>
    @endif

    @if ($pendingSheets->isNotEmpty() && $user?->can('menu', ['staff_attendance', 'approve']))
        <div class="attendance-pending mt-4">
            <strong>Awaiting approval:</strong>
            @foreach ($pendingSheets as $pendingSheet)
                <a href="{{ route('school.staff-attendance.index', ['date' => $pendingSheet->date->toDateString()]) }}">{{ $pendingSheet->date->format('d M') }}</a>
            @endforeach
        </div>
    @endif

    <section class="dashboard-panel attendance-panel mt-4">
        <form class="school-filter-bar attendance-filter-bar attendance-filter-staff" method="GET" action="{{ route('school.staff-attendance.index') }}">
            <div>
                <label class="visually-hidden" for="date">Date</label>
                <input id="date" class="form-control app-form-control" name="date" type="date" value="{{ $date->toDateString() }}" max="{{ $today->toDateString() }}">
            </div>
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search staff</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Name, employee no or designation">
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
            <button class="btn school-filter-button" type="submit">Open</button>
            @if ($hasFilters)<a class="btn school-clear-button" href="{{ route('school.staff-attendance.index', ['date' => $date->toDateString()]) }}">Clear</a>@endif
        </form>

        <div class="attendance-day-title">
            <h2>{{ $date->format('l, d M Y') }}</h2>
            @if ($sheet)<span>{{ $markedCount }} marked</span>@endif
        </div>

        @if ($holiday !== null)
            <div class="attendance-notice">
                <strong>Holiday: {{ $holiday->name }}</strong>
                <span>Attendance is not taken on holidays.</span>
            </div>
        @else
            @include('school.attendance._sheet-status', [
                'sheet' => $sheet,
                'menuKey' => 'staff_attendance',
                'approveRoute' => 'school.staff-attendance.approve',
                'reopenRoute' => 'school.staff-attendance.reopen',
            ])
        @endif

        @if ($employees->isEmpty())
            <div class="school-list-empty">
                <span>S</span>
                <h2>Nobody found</h2>
                <p>{{ $hasFilters ? 'Try changing your filters.' : 'Add teachers and staff first.' }}</p>
            </div>
        @elseif ($holiday === null)
            <form method="POST" action="{{ route('school.staff-attendance.save') }}" data-attendance-form>
                @csrf
                @method('PUT')
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <input type="hidden" name="filter_type" value="{{ $selectedType?->value }}">
                <input type="hidden" name="filter_search" value="{{ $search }}">
                <input type="hidden" name="filter_page" value="{{ $employees->currentPage() }}">

                @if ($editable)
                    <div class="attendance-toolbar">
                        <span>Mark this page as</span>
                        @foreach ($statuses as $statusOption)
                            <button class="btn attendance-choice attendance-choice-{{ $statusOption->value }}" type="button" data-mark-all="{{ $statusOption->value }}" title="Mark this page {{ $statusOption->label() }}">{{ $statusOption->code() }}</button>
                        @endforeach
                        <span class="attendance-legend">P Present · A Absent · L Late · HD Half day · LV Leave</span>
                    </div>
                @endif

                <div class="table-responsive school-table-wrap">
                    <table class="table school-table attendance-table staff-attendance-table align-middle mb-0">
                        <thead><tr><th>Name</th><th>Status</th><th>Check in</th><th>Check out</th><th>Remark</th></tr></thead>
                        <tbody>
                            @foreach ($employees as $employee)
                                @php
                                    $record = $records->get($employee->id);
                                    $old = $oldRows[$employee->id] ?? null;
                                    $current = $old['status'] ?? $record?->status->value ?? 'present';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="school-table-identity">
                                            <span>{{ strtoupper(mb_substr($employee->name, 0, 1)) }}</span>
                                            <div>
                                                <strong>{{ $employee->name }}</strong>
                                                <small>{{ $employee->employee_no }} · {{ $employee->designation ?: $employee->type->label() }}{{ $employee->status->value !== 'active' ? ' · Left' : '' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @include('school.attendance._status-choices', [
                                            'personId' => $employee->id,
                                            'label' => $employee->name,
                                            'current' => $current,
                                            'editable' => $editable,
                                        ])
                                    </td>
                                    <td>
                                        <input class="form-control form-control-sm app-form-control attendance-time @error('attendance.'.$employee->id.'.check_in') is-invalid @enderror" type="time" name="attendance[{{ $employee->id }}][check_in]" value="{{ $old['check_in'] ?? ($record?->check_in ? substr($record->check_in, 0, 5) : '') }}" aria-label="Check in for {{ $employee->name }}" @disabled(! $editable)>
                                    </td>
                                    <td>
                                        <input class="form-control form-control-sm app-form-control attendance-time @error('attendance.'.$employee->id.'.check_out') is-invalid @enderror" type="time" name="attendance[{{ $employee->id }}][check_out]" value="{{ $old['check_out'] ?? ($record?->check_out ? substr($record->check_out, 0, 5) : '') }}" aria-label="Check out for {{ $employee->name }}" @disabled(! $editable)>
                                    </td>
                                    <td>
                                        <input class="form-control form-control-sm app-form-control attendance-remark" name="attendance[{{ $employee->id }}][remark]" value="{{ $old['remark'] ?? $record?->remark }}" maxlength="255" placeholder="Optional" aria-label="Remark for {{ $employee->name }}" @disabled(! $editable)>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($editable)
                    <div class="school-form-actions attendance-form-actions">
                        <span class="text-muted small me-auto">Saving updates the {{ $employees->count() }} people on this page.</span>
                        <button class="btn btn-primary app-btn-primary" type="submit">{{ $sheet ? 'Save changes' : 'Save attendance' }}</button>
                    </div>
                @endif
            </form>
            <div class="school-pagination">{{ $employees->links() }}</div>
        @endif
    </section>
@endsection
