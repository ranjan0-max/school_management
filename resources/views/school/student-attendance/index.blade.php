@extends('layouts.dashboard', ['title' => 'Student Attendance', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php
        $user = auth()->user();
        $canSave = $sheet === null ? $user?->can('menu', ['student_attendance', 'create']) : $user?->can('menu', ['student_attendance', 'edit']);
        $editable = $section !== null && $holiday === null && ! $outsideSession && ! $sheet?->isApproved() && $canSave;
        $oldRows = old('attendance');
    @endphp

    <section class="student-attendance-hero" aria-labelledby="student-attendance-title">
        <div class="student-attendance-hero-main">
            <span class="student-attendance-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M7 3v3m10-3v3M4.5 9h15M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                    <path d="m8.5 14 2 2 4.5-4.5" />
                </svg>
            </span>

            <div class="student-attendance-hero-copy">
                <span class="student-attendance-eyebrow"><i></i> Attendance workspace</span>
                <h1 id="student-attendance-title">Student Attendance</h1>
                <p>Mark daily attendance by section, review exceptions and send the completed sheet for approval.</p>

                <div class="student-attendance-context" aria-label="Current attendance context">
                    <span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" /></svg>
                        {{ $date->format('D, d M Y') }}
                    </span>
                    @if ($session)
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6.5 12 3l8 3.5-8 3.5-8-3.5Zm2.5 3v5.2c3 2.4 8 2.4 11 0V9.5" /></svg>
                            {{ $session->name }}
                        </span>
                    @endif
                    @if ($section)
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM4 10h16M9 10v9" /></svg>
                            {{ $section->class_name }} &ndash; {{ $section->name }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @can('menu', ['attendance_reports', 'view'])
            <a class="student-attendance-report-link" href="{{ route('school.attendance-reports.index', array_filter(['academic_session_id' => $session?->id, 'section_id' => $section?->id, 'month' => $date->format('Y-m')])) }}">
                <span class="student-attendance-report-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M5 20V10m7 10V4m7 16v-7" /></svg>
                </span>
                <span>
                    <small>View insights</small>
                    <strong>Monthly report</strong>
                </span>
                <svg class="student-attendance-report-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
            </a>
        @endcan
    </section>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert">
            <strong>Attendance was not saved.</strong>
            @foreach (array_unique($errors->all()) as $error)<span>{{ $error }}</span>@endforeach
        </div>
    @endif

    <section class="dashboard-panel attendance-panel mt-4">
        <form class="school-filter-bar attendance-filter-bar attendance-filter-student" method="GET" action="{{ route('school.student-attendance.index') }}">
            <div>
                <label class="visually-hidden" for="academic_session_id">Session</label>
                <select id="academic_session_id" class="form-select app-form-control" name="academic_session_id">
                    @forelse ($sessions as $sessionOption)
                        <option value="{{ $sessionOption->id }}" @selected($session?->id === $sessionOption->id)>{{ $sessionOption->name }}{{ $sessionOption->is_current ? ' (current)' : '' }}</option>
                    @empty
                        <option value="">No sessions</option>
                    @endforelse
                </select>
            </div>
            <div class="school-search-field">
                <label class="visually-hidden" for="section_id">Section</label>
                <select id="section_id" class="form-select app-form-control" name="section_id">
                    <option value="">All sections (list of sheets)</option>
                    @foreach ($sections as $sectionOption)
                        <option value="{{ $sectionOption->id }}" @selected($section?->id === $sectionOption->id)>{{ $sectionOption->class_name }} – {{ $sectionOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="date">Date</label>
                <input id="date" class="form-control app-form-control" name="date" type="date" value="{{ $date->toDateString() }}" max="{{ $today->toDateString() }}">
            </div>
            <button class="btn school-filter-button" type="submit">Open</button>
        </form>

        @if ($sessions->isEmpty() || $sections->isEmpty())
            <div class="school-list-empty">
                <span>A</span>
                <h2>Almost ready</h2>
                <p>
                    Attendance needs
                    @if ($sessions->isEmpty()) an <a href="{{ route('school.academic-sessions.index') }}">academic session</a>@endif
                    @if ($sessions->isEmpty() && $sections->isEmpty()) and @endif
                    @if ($sections->isEmpty()) <a href="{{ route('school.sections.index') }}">sections</a>@endif
                    with students enrolled in them.
                </p>
            </div>
        @elseif ($section === null)
            {{-- Recent sheets across sections, e.g. for an approver. --}}
            <div class="attendance-list-heading">
                <h2>Attendance sheets{{ $session ? ' · '.$session->name : '' }}</h2>
                <form class="attendance-status-filter" method="GET" action="{{ route('school.student-attendance.index') }}">
                    <input type="hidden" name="academic_session_id" value="{{ $session?->id }}">
                    <label class="visually-hidden" for="status">Sheet status</label>
                    <select id="status" class="form-select form-select-sm app-form-control" name="status" data-auto-submit>
                        <option value="">All sheets</option>
                        @foreach ($sheetStatuses as $sheetStatusOption)
                            <option value="{{ $sheetStatusOption->value }}" @selected($selectedSheetStatus === $sheetStatusOption)>{{ $sheetStatusOption->label() }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn school-filter-button btn-sm" type="submit">Filter</button></noscript>
                </form>
            </div>

            @if ($sheets->isEmpty())
                <div class="school-list-empty">
                    <span>A</span>
                    <h2>No attendance sheets</h2>
                    <p>Choose a section above to take today's attendance.</p>
                </div>
            @else
                <div class="table-responsive school-table-wrap">
                    <table class="table school-table align-middle mb-0">
                        <thead><tr><th>Date</th><th>Section</th><th class="text-center">Marked</th><th class="text-center">Absent</th><th>Taken by</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody>
                            @foreach ($sheets as $sheetItem)
                                <tr>
                                    <td>{{ $sheetItem->date->format('D, d M Y') }}</td>
                                    <td>{{ $sheetItem->section?->schoolClass?->name }} – {{ $sheetItem->section?->name }}</td>
                                    <td class="text-center"><span class="academic-count-pill">{{ $sheetItem->records_count }}</span></td>
                                    <td class="text-center">{{ $sheetItem->absent_count }}</td>
                                    <td>{{ $sheetItem->takenBy?->name ?? '—' }}</td>
                                    <td><span class="school-status {{ $sheetItem->isApproved() ? 'school-status-active' : 'school-status-suspended' }}">{{ $sheetItem->status->label() }}</span></td>
                                    <td class="text-end">
                                        <a class="btn school-row-action" href="{{ route('school.student-attendance.index', ['academic_session_id' => $sheetItem->academic_session_id, 'section_id' => $sheetItem->section_id, 'date' => $sheetItem->date->toDateString()]) }}">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="school-pagination">{{ $sheets->links() }}</div>
            @endif
        @else
            <div class="attendance-day-title">
                <h2>{{ $section->class_name }} – {{ $section->name }}</h2>
                <span>{{ $date->format('l, d M Y') }} · {{ $session->name }}</span>
            </div>

            @if ($holiday !== null)
                <div class="attendance-notice">
                    <strong>Holiday: {{ $holiday->name }}</strong>
                    <span>Attendance is not taken on holidays.</span>
                </div>
            @elseif ($outsideSession)
                <div class="attendance-notice">
                    <strong>Outside the {{ $session->name }} session</strong>
                    <span>Choose a date between {{ $session->starts_on->format('d M Y') }} and {{ $session->ends_on->format('d M Y') }}, or another session.</span>
                </div>
            @else
                @include('school.attendance._sheet-status', [
                    'sheet' => $sheet,
                    'menuKey' => 'student_attendance',
                    'approveRoute' => 'school.student-attendance.approve',
                    'reopenRoute' => 'school.student-attendance.reopen',
                ])
            @endif

            @if ($students->isEmpty())
                <div class="school-list-empty">
                    <span>S</span>
                    <h2>No students in this section</h2>
                    <p>Enrol students into {{ $section->class_name }} – {{ $section->name }} for {{ $session->name }} from the student form.</p>
                </div>
            @elseif ($holiday === null && ! $outsideSession)
                <form method="POST" action="{{ route('school.student-attendance.save') }}" data-attendance-form>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                    @if ($editable)
                        <div class="attendance-toolbar">
                            <span>Mark everyone as</span>
                            @foreach ($statuses as $statusOption)
                                <button class="btn attendance-choice attendance-choice-{{ $statusOption->value }}" type="button" data-mark-all="{{ $statusOption->value }}" title="Mark everyone {{ $statusOption->label() }}">{{ $statusOption->code() }}</button>
                            @endforeach
                            <span class="attendance-legend">P Present · A Absent · L Late · HD Half day · LV Leave</span>
                        </div>
                    @endif

                    <div class="table-responsive school-table-wrap">
                        <table class="table school-table attendance-table student-attendance-table align-middle mb-0">
                            <thead><tr><th class="text-center">Roll</th><th>Student</th><th>Status</th><th>Remark</th></tr></thead>
                            <tbody>
                                @foreach ($students as $student)
                                    @php
                                        $record = $records->get($student->id);
                                        $current = $oldRows[$student->id]['status'] ?? $record?->status->value ?? 'present';
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $student->roll_no ?? '—' }}</td>
                                        <td>
                                            <div class="school-table-identity">
                                                <span>{{ strtoupper(mb_substr($student->first_name, 0, 1)) }}</span>
                                                <div>
                                                    <strong>{{ $student->fullName() }}</strong>
                                                    <small>{{ $student->admission_no }}{{ $student->status->value !== 'active' ? ' · '.$student->status->label() : '' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @include('school.attendance._status-choices', [
                                                'personId' => $student->id,
                                                'label' => $student->fullName(),
                                                'current' => $current,
                                                'editable' => $editable,
                                            ])
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm app-form-control attendance-remark" name="attendance[{{ $student->id }}][remark]" value="{{ $oldRows[$student->id]['remark'] ?? $record?->remark }}" maxlength="255" placeholder="Optional" aria-label="Remark for {{ $student->fullName() }}" @disabled(! $editable)>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($editable)
                        <div class="school-form-actions attendance-form-actions">
                            <span class="text-muted small me-auto">{{ $students->count() }} student(s)</span>
                            <button class="btn btn-primary app-btn-primary" type="submit">{{ $sheet ? 'Save changes' : 'Save attendance' }}</button>
                        </div>
                    @endif
                </form>
            @endif
        @endif
    </section>
@endsection
