@extends('layouts.dashboard', ['title' => 'Create Role', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">{{ $role->isPlatform() ? 'PLATFORM ROLE' : 'SCHOOL ROLE' }}</span>
            <h1>Create a {{ $role->isPlatform() ? 'platform' : 'school' }} role</h1>
            <p>{{ $role->isPlatform()
                ? 'Reusable in every school and assignable only by Super Admin. In each school it never exceeds that school\'s menus.'
                : 'Usable only inside the selected school, and limited to the menus that school has been given.' }}</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('platform.roles.index') }}">Back to roles</a>
    </div>

    @if (! $role->isPlatform() && $school === null)
        <section class="dashboard-panel mt-4 role-school-picker">
            <div class="account-section-heading">
                <span class="account-section-icon">S</span>
                <div><h2>Choose a school</h2><p>A school role only offers the menus assigned to its school.</p></div>
            </div>
            <form class="school-filter-bar" method="GET" action="{{ route('platform.roles.create') }}">
                <input type="hidden" name="type" value="school">
                <div class="school-search-field">
                    <label class="visually-hidden" for="school_id">School</label>
                    <select id="school_id" class="form-select app-form-control" name="school_id" required>
                        <option value="">Select a school…</option>
                        @foreach ($schools as $schoolOption)
                            <option value="{{ $schoolOption->id }}">{{ $schoolOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary app-btn-primary" type="submit">Continue</button>
            </form>
        </section>
    @else
        <form class="mt-4" method="POST" action="{{ route('platform.roles.store') }}">
            @csrf
            <input type="hidden" name="type" value="{{ $role->type->value }}">
            @if ($school)
                <input type="hidden" name="school_id" value="{{ $school->id }}">
            @endif
            @include('platform.roles._form')
        </form>
    @endif
@endsection
