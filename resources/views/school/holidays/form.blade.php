@php($isEditing = $holiday->exists)

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Holiday' : 'Add Holiday', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">HOLIDAYS</span>
            <h1>{{ $isEditing ? 'Edit '.$holiday->name : 'Add a holiday' }}</h1>
            <p>Leave the end date empty for a one-day holiday.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.holidays.index') }}">Back to holidays</a>
    </div>

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.holidays.update', $holiday) : route('school.holidays.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <section class="dashboard-panel">
            <div class="account-section-heading">
                <span class="account-section-icon">H</span>
                <div><h2>Holiday details</h2><p>For example “Diwali” or “Summer vacation”.</p></div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label app-form-label" for="name">Name <span class="text-danger">*</span></label>
                    <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $holiday->name) }}" maxlength="100" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label app-form-label" for="starts_on">From <span class="text-danger">*</span></label>
                    <input id="starts_on" class="form-control app-form-control @error('starts_on') is-invalid @enderror" name="starts_on" type="date" value="{{ old('starts_on', $holiday->starts_on?->toDateString()) }}" required>
                    @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label app-form-label" for="ends_on">To</label>
                    <input id="ends_on" class="form-control app-form-control @error('ends_on') is-invalid @enderror" name="ends_on" type="date" value="{{ old('ends_on', $holiday->ends_on?->toDateString()) }}">
                    @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label app-form-label" for="description">Description</label>
                    <textarea id="description" class="form-control app-form-control @error('description') is-invalid @enderror" name="description" rows="2" maxlength="2000">{{ old('description', $holiday->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('school.holidays.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save holiday' : 'Add holiday' }}</button>
        </div>
    </form>
@endsection
