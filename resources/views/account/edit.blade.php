@extends('layouts.dashboard', [
    'title' => 'Account Settings',
    'panelLabel' => auth()->user()?->isSuperAdmin() ? 'SUPER ADMIN' : 'SCHOOL WORKSPACE',
    'workspaceName' => auth()->user()?->isSuperAdmin() ? 'Platform Control' : 'School Operations',
])

@section('content')
    <div class="dashboard-page-heading account-page-heading">
        <div>
            <span class="section-kicker">ACCOUNT SECURITY</span>
            <h1>Account settings</h1>
            <p>Keep your identity and sign-in credentials accurate and secure.</p>
        </div>
    </div>

    <div class="row g-4 mt-1 account-grid">
        <div class="col-xl-6">
            <section class="dashboard-panel h-100">
                <div class="account-section-heading">
                    <span class="account-section-icon">A</span>
                    <div>
                        <h2>Profile details</h2>
                        <p>Update your display name and sign-in email.</p>
                    </div>
                </div>

                @if (session('profile_status'))
                    <div class="alert account-alert-success" role="status">{{ session('profile_status') }}</div>
                @endif

                <form method="POST" action="{{ route('account.profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="name">Full name</label>
                        <input id="name" class="form-control app-form-control @error('name', 'updateProfile') is-invalid @enderror" name="name" value="{{ old('name', auth()->user()?->name) }}" required>
                        @error('name', 'updateProfile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="email">Email address</label>
                        <input id="email" class="form-control app-form-control @error('email', 'updateProfile') is-invalid @enderror" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" required>
                        @error('email', 'updateProfile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label app-form-label" for="profile_current_password">Current password</label>
                        <input id="profile_current_password" class="form-control app-form-control @error('current_password', 'updateProfile') is-invalid @enderror" name="current_password" type="password" autocomplete="current-password" required>
                        <div class="form-text account-form-help">Required to protect sensitive account changes.</div>
                        @error('current_password', 'updateProfile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button class="btn btn-primary app-btn-primary" type="submit">Save profile</button>
                </form>
            </section>
        </div>

        <div class="col-xl-6">
            <section class="dashboard-panel h-100">
                <div class="account-section-heading">
                    <span class="account-section-icon secure">P</span>
                    <div>
                        <h2>Change password</h2>
                        <p>Use a unique password you do not use elsewhere.</p>
                    </div>
                </div>

                @if (session('password_status'))
                    <div class="alert account-alert-success" role="status">{{ session('password_status') }}</div>
                @endif

                <form method="POST" action="{{ route('account.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="password_current_password">Current password</label>
                        <input id="password_current_password" class="form-control app-form-control @error('current_password', 'updatePassword') is-invalid @enderror" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="password">New password</label>
                        <input id="password" class="form-control app-form-control @error('password', 'updatePassword') is-invalid @enderror" name="password" type="password" autocomplete="new-password" required>
                        <div class="form-text account-form-help">Minimum 12 characters with uppercase, lowercase, number, and symbol.</div>
                        @error('password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label app-form-label" for="password_confirmation">Confirm new password</label>
                        <input id="password_confirmation" class="form-control app-form-control" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>

                    <button class="btn btn-primary app-btn-primary" type="submit">Update password</button>
                </form>
            </section>
        </div>
    </div>
@endsection
