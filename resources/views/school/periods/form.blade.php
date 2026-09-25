@php($isEditing = $period->exists)

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Period' : 'Add Period', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">PERIODS</span>
            <h1>{{ $isEditing ? 'Edit '.$period->name : 'Add a period' }}</h1>
            <p>Times are optional. Periods appear on the timetable in display order.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.periods.index') }}">Back to periods</a>
    </div>

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.periods.update', $period) : route('school.periods.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <section class="dashboard-panel">
            <div class="account-section-heading">
                <span class="account-section-icon">P</span>
                <div><h2>Period details</h2><p>Names must be unique, for example “Period 1” or “Lunch”.</p></div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label app-form-label" for="name">Name <span class="text-danger">*</span></label>
                    <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $period->name) }}" maxlength="50" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label app-form-label" for="starts_at">Starts at</label>
                    <input id="starts_at" class="form-control app-form-control @error('starts_at') is-invalid @enderror" name="starts_at" type="time" value="{{ old('starts_at', $period->starts_at ? substr($period->starts_at, 0, 5) : '') }}">
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label app-form-label" for="ends_at">Ends at</label>
                    <input id="ends_at" class="form-control app-form-control @error('ends_at') is-invalid @enderror" name="ends_at" type="time" value="{{ old('ends_at', $period->ends_at ? substr($period->ends_at, 0, 5) : '') }}">
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label app-form-label" for="sort_order">Order</label>
                    <input id="sort_order" class="form-control app-form-control @error('sort_order') is-invalid @enderror" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $period->sort_order) }}">
                    @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <input name="is_break" type="hidden" value="0">
                    <label class="role-active-choice" for="is_break">
                        <input id="is_break" class="form-check-input" name="is_break" type="checkbox" value="1" @checked((bool) old('is_break', $period->is_break))>
                        <span><strong>Break</strong><small>Lunch, assembly or recess. No lessons can be placed here, and existing ones are removed.</small></span>
                    </label>
                </div>
            </div>
        </section>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('school.periods.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save period' : 'Add period' }}</button>
        </div>
    </form>
@endsection
