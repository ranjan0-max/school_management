@extends('layouts.dashboard', [
    'title' => 'Platform Dashboard',
    'panelLabel' => 'SUPER ADMIN',
    'workspaceName' => 'Platform Control',
])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">PLATFORM OVERVIEW</span>
            <h1>Good to see you, {{ auth()->user()?->name ?? 'Administrator' }}.</h1>
            <p>Manage schools, their menus, roles and users from one place.</p>
        </div>
        <a class="btn btn-primary app-btn-primary" href="{{ route('platform.schools.create') }}">+ Create school</a>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-md-4">
            <article class="dashboard-metric-card">
                <span class="dashboard-metric-icon purple">S</span>
                <small>Registered schools</small>
                <strong>{{ number_format($schoolCount) }}</strong>
                <p>Schools currently registered</p>
            </article>
        </div>
        <div class="col-md-4">
            <article class="dashboard-metric-card">
                <span class="dashboard-metric-icon blue">R</span>
                <small>Roles</small>
                <strong>{{ number_format($roleCount) }}</strong>
                <p><a href="{{ route('platform.roles.index') }}">Manage platform and school roles</a></p>
            </article>
        </div>
        <div class="col-md-4">
            <article class="dashboard-metric-card">
                <span class="dashboard-metric-icon green">U</span>
                <small>School users</small>
                <strong>{{ number_format($userCount) }}</strong>
                <p><a href="{{ route('platform.users.index') }}">Manage users and their access</a></p>
            </article>
        </div>
    </div>

    <section class="dashboard-panel mt-4">
        <div class="dashboard-panel-heading">
            <div>
                <span class="section-kicker">SCHOOL CONTEXT</span>
                <h2>Open a school workspace</h2>
            </div>
            <span class="dashboard-panel-badge">{{ $schools->count() }} shown</span>
        </div>

        @if ($schools->isEmpty())
            <div class="account-alert-success mt-4 mb-0">
                No schools have been created yet. <a href="{{ route('platform.schools.create') }}">Create the first school</a> to begin.
            </div>
        @else
            <div class="school-switch-list mt-4">
                @foreach ($schools as $schoolItem)
                    <div class="school-switch-row">
                        <span class="school-switch-mark">{{ strtoupper(substr($schoolItem->name, 0, 1)) }}</span>
                        <span class="school-switch-info">
                            <strong>{{ $schoolItem->name }}</strong>
                            <small>{{ $schoolItem->code ?: 'No school code' }} · {{ ucfirst($schoolItem->status->value) }}</small>
                        </span>
                        <form method="POST" action="{{ route('platform.schools.enter', $schoolItem) }}">
                            @csrf
                            <button class="btn school-switch-button" type="submit">Open workspace</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
