@php
    $isEditing = $user->exists;
    $readOnly = $lock === 'platform_role';
    $accessLocked = $lock !== null;
    $selectedRoleId = (int) old('role_id', $user->role_id);
    $selectedStatus = old('status', $user->status?->value ?? 'active');
@endphp

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit User' : 'Add User', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">USERS</span>
            <h1>{{ $isEditing ? ($user->name ?: $user->email) : 'Add a user' }}</h1>
            <p>{{ $isEditing ? $user->email.' · '.($user->role ? 'Role: '.$user->role->name : 'No role assigned') : 'The user signs in with this email and password.' }}</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.users.index') }}">Back to users</a>
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert"><strong>Changes were not saved.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    @if ($lock === 'platform_role')
        <div class="attendance-notice mt-4">
            <strong>Managed by the platform</strong>
            <span>This user holds the platform role “{{ $user->role->name }}”, set by the Super Admin. You can view the account but not change it.</span>
        </div>
    @elseif ($lock === 'self')
        <div class="attendance-notice mt-4">
            <strong>This is your own account</strong>
            <span>You can update your name, email, phone and password. Your role, status and access can only be changed by another administrator.</span>
        </div>
    @endif

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.users.update', $user) : route('school.users.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <fieldset @disabled($readOnly)>
            <div class="row g-4">
                <div class="col-xl-7">
                    <section class="dashboard-panel h-100">
                        <div class="account-section-heading">
                            <span class="account-section-icon">U</span>
                            <div><h2>Profile</h2><p>Email must be unique. Phone is optional.</p></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label app-form-label" for="name">Full name <span class="text-danger">*</span></label>
                                <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label app-form-label" for="phone">Phone</label>
                                <input id="phone" class="form-control app-form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label app-form-label" for="email">Email address <span class="text-danger">*</span></label>
                                <input id="email" class="form-control app-form-control @error('email') is-invalid @enderror" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label app-form-label" for="password">{{ $isEditing ? 'New password' : 'Password' }} @unless ($isEditing)<span class="text-danger">*</span>@endunless</label>
                                <input id="password" class="form-control app-form-control @error('password') is-invalid @enderror" name="password" type="password" autocomplete="new-password" @required(! $isEditing)>
                                <div class="form-text account-form-help">{{ $isEditing ? 'Leave empty to keep the current password.' : 'Minimum 12 characters with upper, lower, number and symbol.' }}</div>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label app-form-label" for="password_confirmation">Confirm password</label>
                                <input id="password_confirmation" class="form-control app-form-control" name="password_confirmation" type="password" autocomplete="new-password">
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-xl-5">
                    <section class="dashboard-panel h-100">
                        <div class="account-section-heading">
                            <span class="account-section-icon secure">A</span>
                            <div><h2>Role &amp; status</h2><p>The role decides what the user can open.</p></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label app-form-label" for="role_id">Role</label>
                            <select id="role_id" class="form-select app-form-control @error('role_id') is-invalid @enderror" name="role_id" @disabled($accessLocked)>
                                <option value="">No role (no access yet)</option>
                                @if ($user->role?->isPlatform())
                                    <option value="" selected>{{ $user->role->name }} (platform)</option>
                                @endif
                                @foreach ($roles as $roleOption)
                                    <option value="{{ $roleOption->id }}" @selected($selectedRoleId === $roleOption->id)>{{ $roleOption->name }}{{ $roleOption->is_active ? '' : ' (inactive)' }}</option>
                                @endforeach
                            </select>
                            <div class="form-text account-form-help">
                                Only this school's roles are listed.
                                @can('menu', ['roles', 'view'])<a href="{{ route('school.roles.index') }}">Manage roles</a>@endcan
                            </div>
                            @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="form-label app-form-label" for="status">Status</label>
                            <select id="status" class="form-select app-form-control @error('status') is-invalid @enderror" name="status" @disabled($accessLocked)>
                                <option value="active" @selected($selectedStatus === 'active')>Active: can sign in</option>
                                <option value="inactive" @selected($selectedStatus !== 'active')>Inactive: cannot sign in</option>
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </section>
                </div>
            </div>

            @if ($isEditing)
                <section class="dashboard-panel mt-4">
                    <div class="dashboard-panel-heading">
                        <div><span class="section-kicker">EXTRA ACCESS</span><h2>Individual menu access</h2></div>
                        <span class="dashboard-panel-badge">Deny always wins</span>
                    </div>
                    <p class="role-permission-intro">
                        Leave cells on <strong>Role</strong> for normal access. Choose <strong>Allow</strong> to give this user something the role does not,
                        or <strong>Deny</strong> to take something away. The small tick or cross shows what the role gives today.
                    </p>

                    @include('partials.access.override-matrix', [
                        'disabled' => $accessLocked,
                        'emptyMessage' => 'No menus are assigned to '.$school->name.' yet. Ask the platform administrator.',
                    ])
                </section>
            @endif
        </fieldset>

        @unless ($readOnly)
            <div class="school-form-actions">
                <a class="btn school-secondary-button" href="{{ route('school.users.index') }}">Cancel</a>
                <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save user' : 'Create user' }}</button>
            </div>
        @endunless
    </form>
@endsection
