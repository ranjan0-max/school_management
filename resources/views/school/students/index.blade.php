@extends('layouts.dashboard', ['title' => 'Students', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php($hasFilters = $search !== '' || $selectedClassId !== null || $selectedSectionId !== null || $selectedStatus !== null)

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">PEOPLE MANAGEMENT</span>
            <h1>Students</h1>
            <p>Class, section and roll number are shown for the selected session{{ $session ? ' ('.$session->name.')' : '' }}.</p>
        </div>
        @can('menu', ['students', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.students.create') }}">+ Add student</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar student-filter-bar" method="GET" action="{{ route('school.students.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search students</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Name, admission no or phone">
            </div>
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
            <div>
                <label class="visually-hidden" for="class_id">Class</label>
                <select id="class_id" class="form-select app-form-control" name="class_id">
                    <option value="">All classes</option>
                    @foreach ($classes as $classOption)
                        <option value="{{ $classOption->id }}" @selected($selectedClassId === $classOption->id)>{{ $classOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="section_id">Section</label>
                <select id="section_id" class="form-select app-form-control" name="section_id">
                    <option value="">All sections</option>
                    @foreach ($sections as $sectionOption)
                        <option value="{{ $sectionOption->id }}" @selected($selectedSectionId === $sectionOption->id)>{{ $sectionOption->class_name }} – {{ $sectionOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="status">Status</label>
                <select id="status" class="form-select app-form-control" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected($selectedStatus === $statusOption)>{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($hasFilters)<a class="btn school-clear-button" href="{{ route('school.students.index', ['academic_session_id' => $session?->id]) }}">Clear</a>@endif
        </form>

        @if ($students->isEmpty())
            <div class="school-list-empty">
                <span>S</span>
                <h2>No students found</h2>
                <p>{{ $hasFilters ? 'Try changing your filters.' : 'Add the first student. Only the first name is required.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Student</th><th>Admission no</th><th>Class</th><th class="text-center">Roll</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($students as $studentItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(substr($studentItem->first_name, 0, 1)) }}</span>
                                        <div><strong>{{ $studentItem->fullName() }}</strong><small>{{ $studentItem->phone ?: ($studentItem->gender ? ucfirst($studentItem->gender->value) : '—') }}</small></div>
                                    </div>
                                </td>
                                <td><code>{{ $studentItem->admission_no }}</code></td>
                                <td>{{ $studentItem->class_name ? $studentItem->class_name.' – '.$studentItem->section_name : 'Not placed' }}</td>
                                <td class="text-center">{{ $studentItem->roll_no ?? '—' }}</td>
                                <td><span class="school-status {{ $studentItem->status->value === 'active' ? 'school-status-active' : 'school-status-cancelled' }}">{{ $studentItem->status->label() }}</span></td>
                                <td class="text-end">
                                    @can('menu', ['students', 'edit'])
                                        <a class="btn school-row-action" href="{{ route('school.students.edit', $studentItem) }}">Edit</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $students->links() }}</div>
        @endif
    </section>
@endsection
