@extends('layouts.dashboard', ['title' => 'Edit School', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">SCHOOL MANAGEMENT</span>
            <h1>Edit {{ $school->name }}</h1>
            <p>Manage identity, operational status, locale and plan limits.</p>
        </div>
        <div class="school-heading-actions">
            <a class="btn school-access-button" href="{{ route('platform.schools.access', $school) }}">Manage menus</a>
            <a class="btn school-secondary-button" href="{{ route('platform.roles.index', ['type' => 'school', 'school_id' => $school->id]) }}">Roles</a>
            <a class="btn school-secondary-button" href="{{ route('platform.users.index', ['school_id' => $school->id]) }}">Users</a>
            <form method="POST" action="{{ route('platform.schools.enter', $school) }}">@csrf<button class="btn school-secondary-button" type="submit">Open workspace</button></form>
        </div>
    </div>
    @if (session('status'))
        <div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>
    @endif
    <form class="mt-4" method="POST" action="{{ route('platform.schools.update', $school) }}">
        @csrf
        @method('PUT')
        @include('platform.schools._form')
    </form>
@endsection
