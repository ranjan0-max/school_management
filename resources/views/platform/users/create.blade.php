@extends('layouts.dashboard', ['title' => 'Add User', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">USERS</span>
            <h1>Add a user</h1>
            <p>Create the login and choose the school and role. Individual menu access can be added after saving.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('platform.users.index') }}">Back to users</a>
    </div>

    <form class="mt-4" method="POST" action="{{ route('platform.users.store') }}">
        @csrf
        @include('platform.users._form')
        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('platform.users.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">Create user</button>
        </div>
    </form>
@endsection
