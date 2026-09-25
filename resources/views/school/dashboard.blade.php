@extends('layouts.dashboard', [
    'title' => 'School Dashboard',
    'panelLabel' => 'SCHOOL WORKSPACE',
    'workspaceName' => $school->name,
])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">{{ strtoupper($school->code ?: 'SCHOOL OVERVIEW') }}</span>
            <h1>Welcome, {{ auth()->user()?->name ?? 'Team Member' }}.</h1>
            <p>{{ $school->name }} · Your assigned modules and role-based navigation will appear here.</p>
        </div>
        <span class="dashboard-date">{{ now()->format('l, d M Y') }}</span>
    </div>

    <section class="dashboard-empty-state mt-4">
        <span class="dashboard-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 20V8l8-4 8 4v12M8 20v-5h8v5M8 10h.01M12 10h.01M16 10h.01" /></svg>
        </span>
        <span class="section-kicker">WORKSPACE READY</span>
        <h2>School modules will appear here</h2>
        <p>Once the Super Admin assigns modules and menus, this dashboard will adapt automatically to your access.</p>
    </section>

    @if (auth()->user()?->isSuperAdmin())
        <form class="text-center mt-4" method="POST" action="{{ route('platform.schools.leave') }}">
            @csrf
            @method('DELETE')
            <button class="btn school-switch-button" type="submit">Return to Platform Control</button>
        </form>
    @endif
@endsection
