@extends('layouts.dashboard', ['title' => 'Create School', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">SCHOOL MANAGEMENT</span>
            <h1>Create a school</h1>
            <p>Register a new tenant workspace. Modules and menus can be assigned afterwards.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('platform.schools.index') }}">Back to schools</a>
    </div>
    <form class="mt-4" method="POST" action="{{ route('platform.schools.store') }}">
        @csrf
        @include('platform.schools._form')
    </form>
@endsection
