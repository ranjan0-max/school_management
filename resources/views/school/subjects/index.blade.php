@extends('layouts.dashboard', ['title' => 'Subjects', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ACADEMIC MANAGEMENT</span>
            <h1>Subjects</h1>
            <p>The school's subject list. Attach subjects to classes from each class's edit screen.</p>
        </div>
        @can('menu', ['subjects', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.subjects.create') }}">+ Add subject</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert school-access-error mt-4 mb-0" role="alert"><span>{{ session('error') }}</span></div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.subjects.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search subjects</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by name or code">
            </div>
            <div>
                <label class="visually-hidden" for="type">Type</label>
                <select id="type" class="form-select app-form-control" name="type">
                    <option value="">All types</option>
                    @foreach ($types as $typeOption)
                        <option value="{{ $typeOption->value }}" @selected($selectedType === $typeOption)>{{ ucfirst($typeOption->value) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($search !== '' || $selectedType !== null)<a class="btn school-clear-button" href="{{ route('school.subjects.index') }}">Clear</a>@endif
        </form>

        @if ($subjects->isEmpty())
            <div class="school-list-empty">
                <span>S</span>
                <h2>No subjects</h2>
                <p>{{ $search !== '' || $selectedType !== null ? 'No subject matches your filters.' : 'Add subjects such as English, Maths and Science.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Subject</th><th>Type</th><th class="text-center">Classes</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($subjects as $subjectItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(substr($subjectItem->name, 0, 1)) }}</span>
                                        <div><strong>{{ $subjectItem->name }}</strong><small>{{ $subjectItem->code ?: 'No code' }}</small></div>
                                    </div>
                                </td>
                                <td><span class="school-status {{ $subjectItem->type->value === 'practical' ? 'school-status-trial' : 'school-status-active' }}">{{ ucfirst($subjectItem->type->value) }}</span></td>
                                <td class="text-center"><span class="academic-count-pill">{{ $subjectItem->classes_count }}</span></td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        @can('menu', ['subjects', 'edit'])
                                            <a class="btn school-row-action" href="{{ route('school.subjects.edit', $subjectItem) }}">Edit</a>
                                        @endcan
                                        @can('menu', ['subjects', 'delete'])
                                            <form method="POST" action="{{ route('school.subjects.destroy', $subjectItem) }}" data-confirm="Delete {{ $subjectItem->name }}? It will also be removed from {{ $subjectItem->classes_count }} class(es).">
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
            <div class="school-pagination">{{ $subjects->links() }}</div>
        @endif
    </section>
@endsection
