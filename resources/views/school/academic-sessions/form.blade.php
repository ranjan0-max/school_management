@php($isEditing = $session->exists)

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Session' : 'Add Session', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ACADEMIC SESSIONS</span>
            <h1>{{ $isEditing ? 'Edit '.$session->name : 'Add a session' }}</h1>
            <p>Give the school year a name and its start and end dates.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.academic-sessions.index') }}">Back to sessions</a>
    </div>

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.academic-sessions.update', $session) : route('school.academic-sessions.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <section class="dashboard-panel">
            <div class="account-section-heading">
                <span class="account-section-icon">S</span>
                <div><h2>Session details</h2><p>Only one session can be current at a time.</p></div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label app-form-label" for="name">Session name <span class="text-danger">*</span></label>
                    <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $session->name) }}" maxlength="50" placeholder="2026-27" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label app-form-label" for="starts_on">Starts on <span class="text-danger">*</span></label>
                    <input id="starts_on" class="form-control app-form-control @error('starts_on') is-invalid @enderror" name="starts_on" type="date" value="{{ old('starts_on', $session->starts_on?->toDateString()) }}" required>
                    @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label app-form-label" for="ends_on">Ends on <span class="text-danger">*</span></label>
                    <input id="ends_on" class="form-control app-form-control @error('ends_on') is-invalid @enderror" name="ends_on" type="date" value="{{ old('ends_on', $session->ends_on?->toDateString()) }}" required>
                    @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <input name="is_current" type="hidden" value="0">
                    <label class="role-active-choice" for="is_current">
                        <input id="is_current" class="form-check-input" name="is_current" type="checkbox" value="1" @checked((bool) old('is_current', $session->is_current))>
                        <span><strong>Current session</strong><small>Marking this as current removes the mark from any other session.</small></span>
                    </label>
                </div>
            </div>
        </section>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('school.academic-sessions.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save session' : 'Create session' }}</button>
        </div>
    </form>
@endsection
