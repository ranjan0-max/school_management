@extends('layouts.dashboard', ['title' => 'Edit User', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">{{ strtoupper($user->school?->name ?? 'USER') }}</span>
            <h1>{{ $user->name ?: $user->email }}</h1>
            <p>{{ $user->email }} &middot; {{ $user->role ? 'Role: '.$user->role->name : 'No role assigned' }}</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('platform.users.index', ['school_id' => $user->school_id]) }}">Back to users</a>
    </div>

    @if (session('status'))
        <div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert"><strong>Changes were not saved.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <form class="mt-4" method="POST" action="{{ route('platform.users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('platform.users._form')

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
                'emptyMessage' => $user->school ? $user->school->name.' has no menus yet.' : 'This user has no school.',
                'assignMenusUrl' => $user->school ? route('platform.schools.access', $user->school) : null,
            ])
        </section>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('platform.users.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">Save user</button>
        </div>
    </form>
@endsection
