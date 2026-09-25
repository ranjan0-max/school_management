@extends('layouts.dashboard', ['title' => 'Sections', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ACADEMIC MANAGEMENT</span>
            <h1>Sections</h1>
            <p>Divisions of a class such as 5-A and 5-B, with an optional seat capacity.</p>
        </div>
        @can('menu', ['sections', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.sections.create', ['class_id' => $selectedClassId]) }}">+ Add section</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert school-access-error mt-4 mb-0" role="alert"><span>{{ session('error') }}</span></div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.sections.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search sections</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by section name">
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
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($search !== '' || $selectedClassId !== null)<a class="btn school-clear-button" href="{{ route('school.sections.index') }}">Clear</a>@endif
        </form>

        @if ($sections->isEmpty())
            <div class="school-list-empty">
                <span>S</span>
                <h2>No sections</h2>
                <p>
                    @if ($classes->isEmpty())
                        Add classes first, then create their sections.
                    @elseif ($search !== '' || $selectedClassId !== null)
                        No section matches your filters.
                    @else
                        Add sections such as A and B to your classes.
                    @endif
                </p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Section</th><th>Class</th><th class="text-center">Capacity</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($sections as $sectionItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(substr($sectionItem->name, 0, 2)) }}</span>
                                        <div><strong>{{ $sectionItem->class_name }} – {{ $sectionItem->name }}</strong><small>Section {{ $sectionItem->name }}</small></div>
                                    </div>
                                </td>
                                <td>{{ $sectionItem->class_name }}</td>
                                <td class="text-center">{{ $sectionItem->capacity ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        @can('menu', ['sections', 'edit'])
                                            <a class="btn school-row-action" href="{{ route('school.sections.edit', $sectionItem) }}">Edit</a>
                                        @endcan
                                        @can('menu', ['sections', 'delete'])
                                            <form method="POST" action="{{ route('school.sections.destroy', $sectionItem) }}" data-confirm="Delete section {{ $sectionItem->class_name }} – {{ $sectionItem->name }}?">
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
            <div class="school-pagination">{{ $sections->links() }}</div>
        @endif
    </section>
@endsection
