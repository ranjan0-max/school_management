@php
    $isEditing = $user->exists;
    $selectedSchoolId = (int) old('school_id', $user->school_id);
    $selectedRoleId = (int) old('role_id', $user->role_id);
    $selectedStatus = old('status', $user->status?->value ?? 'active');
@endphp

<div class="row g-4">
    <div class="col-xl-7">
        <section class="dashboard-panel h-100">
            <div class="account-section-heading">
                <span class="account-section-icon">U</span>
                <div><h2>Profile</h2><p>Login details for this user. Email must be unique across the platform.</p></div>
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
                <div><h2>School &amp; role</h2><p>A user belongs to one school and holds one role.</p></div>
            </div>

            <div class="mb-3">
                <label class="form-label app-form-label" for="school_id">School <span class="text-danger">*</span></label>
                <select id="school_id" class="form-select app-form-control @error('school_id') is-invalid @enderror" name="school_id" data-school-select required>
                    <option value="">Select a school…</option>
                    @foreach ($schools as $schoolOption)
                        <option value="{{ $schoolOption->id }}" @selected($selectedSchoolId === $schoolOption->id)>{{ $schoolOption->name }}</option>
                    @endforeach
                </select>
                @error('school_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label app-form-label" for="role_id">Role</label>
                <select id="role_id" class="form-select app-form-control @error('role_id') is-invalid @enderror" name="role_id" data-role-select data-options-url="{{ route('platform.roles.options') }}">
                    <option value="">No role (no access yet)</option>
                    <optgroup label="Platform roles">
                        @foreach ($roles->filter->isPlatform() as $roleOption)
                            <option value="{{ $roleOption->id }}" @selected($selectedRoleId === $roleOption->id)>{{ $roleOption->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="School roles" data-school-roles>
                        @foreach ($roles->reject->isPlatform() as $roleOption)
                            <option value="{{ $roleOption->id }}" @selected($selectedRoleId === $roleOption->id)>{{ $roleOption->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
                <div class="form-text account-form-help">Only platform roles and roles of the selected school can be chosen.</div>
                @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label app-form-label" for="status">Status</label>
                <select id="status" class="form-select app-form-control @error('status') is-invalid @enderror" name="status" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </section>
    </div>
</div>
