@extends('layouts.dashboard', ['title' => 'Timetable', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php
        $canEdit = auth()->user()?->can('menu', ['timetable', 'edit']) ?? false;
        $oldSlots = old('slots');
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ACADEMIC MANAGEMENT</span>
            <h1>Timetable</h1>
            <p>Choose a session and section, then set the subject and teacher for every period of the week.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.periods.index') }}">Manage periods</a>
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert">
            <strong>Timetable was not saved.</strong>
            @foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach
        </div>
    @endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.timetable.index') }}">
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
            <button class="btn school-filter-button" type="submit">Show timetable</button>
        </form>

        @if ($sessions->isEmpty() || $sections->isEmpty() || $periods->isEmpty())
            <div class="school-list-empty">
                <span>T</span>
                <h2>Almost ready</h2>
                <p>
                    A timetable needs
                    @if ($sessions->isEmpty()) an <a href="{{ route('school.academic-sessions.index') }}">academic session</a>, @endif
                    @if ($sections->isEmpty()) <a href="{{ route('school.sections.index') }}">sections</a>, @endif
                    @if ($periods->isEmpty()) <a href="{{ route('school.periods.index') }}">periods</a>, @endif
                    and subjects and teachers to choose from.
                </p>
            </div>
        @elseif ($section === null)
            <div class="school-list-empty">
                <span>T</span>
                <h2>Choose a section</h2>
                <p>Pick a section above to view or edit its weekly timetable.</p>
            </div>
        @else
            <form method="POST" action="{{ route('school.timetable.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
                <input type="hidden" name="section_id" value="{{ $section->id }}">

                <div class="table-responsive school-table-wrap">
                    <table class="table access-matrix timetable-grid mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Period</th>
                                @foreach ($days as $dayName)
                                    <th scope="col">{{ $dayName }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($periods as $period)
                                <tr class="{{ $period->is_break ? 'timetable-break' : '' }}">
                                    <th scope="row">
                                        {{ $period->name }}
                                        @if ($period->timeRange() !== '')<small class="d-block text-muted fw-normal">{{ $period->timeRange() }}</small>@endif
                                    </th>
                                    @if ($period->is_break)
                                        <td colspan="{{ count($days) }}" class="text-center text-muted">Break</td>
                                    @else
                                        @foreach ($days as $dayNumber => $dayName)
                                            @php
                                                $entry = $entries->get($dayNumber)?->get($period->id);
                                                $subjectId = (int) ($oldSlots[$dayNumber][$period->id]['subject_id'] ?? $entry?->subject_id);
                                                $teacherId = (int) ($oldSlots[$dayNumber][$period->id]['teacher_id'] ?? $entry?->teacher_id);
                                            @endphp
                                            <td class="timetable-cell">
                                                <select class="form-select timetable-select" name="slots[{{ $dayNumber }}][{{ $period->id }}][subject_id]" aria-label="{{ $dayName }} {{ $period->name }} subject" @disabled(! $canEdit)>
                                                    <option value="">Subject</option>
                                                    @foreach ($subjects as $subject)
                                                        <option value="{{ $subject->id }}" @selected($subjectId === $subject->id)>{{ $subject->code ?: $subject->name }}</option>
                                                    @endforeach
                                                </select>
                                                <select class="form-select timetable-select" name="slots[{{ $dayNumber }}][{{ $period->id }}][teacher_id]" aria-label="{{ $dayName }} {{ $period->name }} teacher" @disabled(! $canEdit)>
                                                    <option value="">Teacher</option>
                                                    @foreach ($teachers as $teacher)
                                                        <option value="{{ $teacher->id }}" @selected($teacherId === $teacher->id)>{{ $teacher->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($canEdit)
                    <div class="school-form-actions">
                        <span class="text-muted small me-auto">{{ $section->class_name }} – {{ $section->name }} · {{ $session->name }}</span>
                        <button class="btn btn-primary app-btn-primary" type="submit">Save timetable</button>
                    </div>
                @endif
            </form>
        @endif
    </section>
@endsection
