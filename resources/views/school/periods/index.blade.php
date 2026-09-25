@extends('layouts.dashboard', ['title' => 'Periods', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">TIMETABLE</span>
            <h1>Periods</h1>
            <p>The school's daily bell schedule. Mark lunch or assembly as a break so no lesson is placed there.</p>
        </div>
        <div class="school-heading-actions">
            <a class="btn school-secondary-button" href="{{ route('school.timetable.index') }}">Back to timetable</a>
            @can('menu', ['timetable', 'create'])
                <a class="btn btn-primary app-btn-primary" href="{{ route('school.periods.create') }}">+ Add period</a>
            @endcan
        </div>
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif

    <section class="dashboard-panel mt-4">
        @if ($periods->isEmpty())
            <div class="school-list-empty">
                <span>P</span>
                <h2>No periods yet</h2>
                <p>Add periods such as Period 1 (08:00 – 08:40) before building timetables.</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Period</th><th>Time</th><th>Type</th><th class="text-center">Lessons</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($periods as $periodItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ $periodItem->sort_order }}</span>
                                        <div><strong>{{ $periodItem->name }}</strong><small>Order {{ $periodItem->sort_order }}</small></div>
                                    </div>
                                </td>
                                <td>{{ $periodItem->timeRange() ?: '—' }}</td>
                                <td><span class="school-status {{ $periodItem->is_break ? 'school-status-suspended' : 'school-status-active' }}">{{ $periodItem->is_break ? 'Break' : 'Lesson' }}</span></td>
                                <td class="text-center"><span class="academic-count-pill">{{ $periodItem->entries_count }}</span></td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        @can('menu', ['timetable', 'edit'])
                                            <a class="btn school-row-action" href="{{ route('school.periods.edit', $periodItem) }}">Edit</a>
                                        @endcan
                                        @can('menu', ['timetable', 'delete'])
                                            <form method="POST" action="{{ route('school.periods.destroy', $periodItem) }}" data-confirm="Delete {{ $periodItem->name }}? {{ $periodItem->entries_count }} timetable lesson(s) in this period will also be removed.">
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
        @endif
    </section>
@endsection
