@extends('layouts.dashboard', ['title' => 'Classes', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ACADEMIC MANAGEMENT</span>
            <h1>Classes</h1>
            <p>Grades such as Nursery or Class 5, in the order they should appear, with the subjects each class studies.</p>
        </div>
        @can('menu', ['classes', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.classes.create') }}">+ Add class</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert school-access-error mt-4 mb-0" role="alert"><span>{{ session('error') }}</span></div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.classes.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search classes</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by class name">
            </div>
            <button class="btn school-filter-button" type="submit">Search</button>
            @if ($search !== '')<a class="btn school-clear-button" href="{{ route('school.classes.index') }}">Clear</a>@endif
        </form>

        @if ($classes->isEmpty())
            <div class="school-list-empty">
                <span>C</span>
                <h2>No classes</h2>
                <p>{{ $search !== '' ? 'No class matches your search.' : 'Add classes such as Nursery, Class 1 and Class 2.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Class</th><th class="text-center">Sections</th><th class="text-center">Subjects</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($classes as $classItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ $classItem->sort_order }}</span>
                                        <div><strong>{{ $classItem->name }}</strong><small>Display order {{ $classItem->sort_order }}</small></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a class="academic-count-pill text-decoration-none" href="{{ route('school.sections.index', ['class_id' => $classItem->id]) }}" title="View sections">{{ $classItem->sections_count }}</a>
                                </td>
                                <td class="text-center"><span class="academic-count-pill">{{ $classItem->subjects_count }}</span></td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        @can('menu', ['classes', 'edit'])
                                            <a class="btn school-row-action" href="{{ route('school.classes.edit', $classItem) }}">Edit</a>
                                        @endcan
                                        @can('menu', ['classes', 'delete'])
                                            <form method="POST" action="{{ route('school.classes.destroy', $classItem) }}" data-confirm="Delete {{ $classItem->name }}?">
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
            <div class="school-pagination">{{ $classes->links() }}</div>
        @endif
    </section>
@endsection
