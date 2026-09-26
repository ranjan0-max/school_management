@extends('layouts.dashboard', ['title' => 'Guardians', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">PEOPLE MANAGEMENT</span>
            <h1>Guardians</h1>
            <p>Parents and guardians. One guardian can be linked to several students, so siblings share the same record.</p>
        </div>
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert school-access-error mt-4 mb-0" role="alert"><span>{{ session('error') }}</span></div>@endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('school.guardians.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search guardians</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by name, phone or email">
            </div>
            <button class="btn school-filter-button" type="submit">Search</button>
            @if ($search !== '')<a class="btn school-clear-button" href="{{ route('school.guardians.index') }}">Clear</a>@endif
        </form>

        @if ($guardians->isEmpty())
            <div class="school-list-empty">
                <span>G</span>
                <h2>No guardians found</h2>
                <p>{{ $search !== '' ? 'No guardian matches your search.' : 'Guardians are also created automatically when you add a student.' }}</p>
            </div>
        @else
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Guardian</th><th>Phone</th><th>Occupation</th><th class="text-center">Students</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($guardians as $guardianItem)
                            <tr>
                                <td>
                                    <div class="school-table-identity">
                                        <span>{{ strtoupper(substr($guardianItem->name, 0, 1)) }}</span>
                                        <div><strong>{{ $guardianItem->name }}</strong><small>{{ $guardianItem->email ?: '—' }}</small></div>
                                    </div>
                                </td>
                                <td>{{ $guardianItem->phone ?: '—' }}</td>
                                <td>{{ $guardianItem->occupation ?: '—' }}</td>
                                <td class="text-center"><span class="academic-count-pill">{{ $guardianItem->students_count }}</span></td>
                                <td class="text-end">
                                    <div class="school-row-actions">
                                        @can('menu', ['guardians', 'edit'])
                                            <a class="btn school-row-action" href="{{ route('school.guardians.edit', $guardianItem) }}">Edit</a>
                                        @endcan
                                        @can('menu', ['guardians', 'delete'])
                                            @if ($guardianItem->students_count === 0)
                                                <form method="POST" action="{{ route('school.guardians.destroy', $guardianItem) }}" data-confirm="Delete guardian {{ $guardianItem->name }}?">
                                                    @csrf @method('DELETE')
                                                    <button class="btn school-row-action school-row-action-danger" type="submit">Delete</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $guardians->links() }}</div>
        @endif
    </section>
@endsection
