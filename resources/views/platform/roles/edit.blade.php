@extends('layouts.dashboard', ['title' => 'Edit Role', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">{{ $role->isPlatform() ? 'PLATFORM ROLE' : strtoupper($school?->name ?? 'SCHOOL ROLE') }}</span>
            <h1>{{ $role->name }}</h1>
            <p>{{ $role->isPlatform() ? 'Changes apply immediately in every school where this role is assigned.' : 'Changes apply immediately to every user holding this role.' }}</p>
        </div>
        <div class="school-heading-actions">
            <a class="btn school-secondary-button" href="{{ route('platform.users.index', $role->isPlatform() ? [] : ['school_id' => $role->school_id]) }}">Users</a>
            <a class="btn school-secondary-button" href="{{ route('platform.roles.index') }}">Back to roles</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>
    @endif

    <form class="mt-4" method="POST" action="{{ route('platform.roles.update', $role) }}">
        @csrf
        @method('PUT')
        @include('platform.roles._form')
    </form>
@endsection
