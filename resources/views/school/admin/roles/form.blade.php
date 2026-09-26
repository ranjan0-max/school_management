@php($isEditing = $role->exists)

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Role' : 'Add Role', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ROLES &amp; ACCESS</span>
            <h1>{{ $isEditing ? $role->name : 'Add a role' }}</h1>
            <p>Only menus given to {{ $school->name }} are listed. A dash means the action does not apply to that page.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.roles.index') }}">Back to roles</a>
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert"><strong>Changes were not saved.</strong><span>{{ $errors->first() }}</span></div>
    @endif
    @if ($isOwnRole)
        <div class="attendance-notice mt-4">
            <strong>This is your own role</strong>
            <span>You cannot change the role you hold. Another administrator can.</span>
        </div>
    @endif

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.roles.update', $role) : route('school.roles.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <fieldset @disabled($isOwnRole)>
            <div class="row g-4">
                <div class="col-xl-4">
                    <section class="dashboard-panel role-details-panel">
                        <div class="account-section-heading">
                            <span class="account-section-icon">R</span>
                            <div><h2>Role details</h2><p>Names cannot repeat a platform role.</p></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label app-form-label" for="name">Role name <span class="text-danger">*</span></label>
                            <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $role->name) }}" maxlength="255" placeholder="Teacher" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label app-form-label" for="description">Description</label>
                            <textarea id="description" class="form-control app-form-control school-address @error('description') is-invalid @enderror" name="description" maxlength="2000" rows="3">{{ old('description', $role->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <input name="all_school_menus" type="hidden" value="0">
                        <label class="role-active-choice mb-3" for="all_school_menus">
                            <input id="all_school_menus" class="form-check-input" name="all_school_menus" type="checkbox" value="1" @checked((bool) old('all_school_menus', $role->all_school_menus))>
                            <span><strong>All school menus</strong><small>Every action on every menu the school has, including menus added later. The table is ignored while this is on.</small></span>
                        </label>

                        <input name="is_active" type="hidden" value="0">
                        <label class="role-active-choice" for="is_active">
                            <input id="is_active" class="form-check-input" name="is_active" type="checkbox" value="1" @checked((bool) old('is_active', $role->is_active ?? true))>
                            <span><strong>Active role</strong><small>Turn off to take this role's access away from everyone holding it. Roles are never deleted.</small></span>
                        </label>
                    </section>
                </div>

                <div class="col-xl-8">
                    <section class="dashboard-panel">
                        <div class="dashboard-panel-heading">
                            <div><span class="section-kicker">MENU ACCESS</span><h2>What this role can do</h2></div>
                            <span class="dashboard-panel-badge">School boundary enforced</span>
                        </div>

                        @include('partials.access.role-matrix', [
                            'emptyMessage' => 'No menus are assigned to '.$school->name.' yet. Ask the platform administrator.',
                        ])
                    </section>
                </div>
            </div>
        </fieldset>

        @unless ($isOwnRole)
            <div class="school-form-actions">
                <a class="btn school-secondary-button" href="{{ route('school.roles.index') }}">Cancel</a>
                <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save role' : 'Create role' }}</button>
            </div>
        @endunless
    </form>
@endsection
