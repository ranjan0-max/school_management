@extends('layouts.dashboard', ['title' => 'Attendance Reports', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php($filters = array_filter(['academic_session_id' => $session?->id, 'section_id' => $section?->id, 'month' => $month->format('Y-m')]))

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ATTENDANCE</span>
            <h1>Attendance Reports</h1>
            <p>Monthly register of a section: day-wise marks, totals and attendance percentage.</p>
        </div>
        @if ($section !== null)
            @can('menu', ['attendance_reports', 'export'])
                <a class="btn school-secondary-button" href="{{ route('school.attendance-reports.export', $filters) }}">Export CSV</a>
            @endcan
        @endif
    </div>

    @include('school.attendance-reports._tabs')

    <section class="dashboard-panel attendance-panel mt-3">
        <form class="school-filter-bar attendance-filter-bar attendance-filter-student" method="GET" action="{{ route('school.attendance-reports.index') }}">
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
                    <option value="">Select a section…</option>
                    @foreach ($sections as $sectionOption)
                        <option value="{{ $sectionOption->id }}" @selected($section?->id === $sectionOption->id)>{{ $sectionOption->class_name }} – {{ $sectionOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="month">Month</label>
                <input id="month" class="form-control app-form-control" name="month" type="month" value="{{ $month->format('Y-m') }}">
            </div>
            <button class="btn school-filter-button" type="submit">Show report</button>
        </form>

        @if ($section === null || $session === null)
            <div class="school-list-empty">
                <span>R</span>
                <h2>Choose a section</h2>
                <p>Pick a section and a month to see its attendance register.</p>
            </div>
        @elseif ($students->isEmpty())
            <div class="school-list-empty">
                <span>R</span>
                <h2>No students in this section</h2>
                <p>Nobody is enrolled in {{ $section->class_name }} – {{ $section->name }} for {{ $session->name }}.</p>
            </div>
        @else
            <div class="attendance-day-title">
                <h2>{{ $section->class_name }} – {{ $section->name }}</h2>
                <span>{{ $month->format('F Y') }} · {{ $session->name }}</span>
            </div>

            @include('school.attendance-reports._register', [
                'leadHeading' => 'Roll · Student',
                'people' => $students->getCollection()->map(fn ($student) => [
                    'id' => $student->id,
                    'lead' => $student->roll_no,
                    'name' => $student->fullName(),
                    'meta' => $student->admission_no.($student->status->value !== 'active' ? ' · '.$student->status->label() : ''),
                ]),
            ])
            <div class="school-pagination">{{ $students->links() }}</div>
        @endif
    </section>
@endsection
