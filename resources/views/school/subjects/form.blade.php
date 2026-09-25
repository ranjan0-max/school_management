@php($isEditing = $subject->exists)

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Subject' : 'Add Subject', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">SUBJECTS</span>
            <h1>{{ $isEditing ? 'Edit '.$subject->name : 'Add a subject' }}</h1>
            <p>Name, an optional short code, and whether it is theory or practical.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.subjects.index') }}">Back to subjects</a>
    </div>

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.subjects.update', $subject) : route('school.subjects.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <section class="dashboard-panel">
            <div class="account-section-heading">
                <span class="account-section-icon">S</span>
                <div><h2>Subject details</h2><p>Name and code must be unique within the school.</p></div>
            </div>

            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label app-form-label" for="name">Subject name <span class="text-danger">*</span></label>
                    <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $subject->name) }}" maxlength="100" placeholder="Mathematics" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label app-form-label" for="code">Code</label>
                    <input id="code" class="form-control app-form-control text-uppercase @error('code') is-invalid @enderror" name="code" value="{{ old('code', $subject->code) }}" maxlength="30" placeholder="MATH">
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label app-form-label" for="type">Type <span class="text-danger">*</span></label>
                    <select id="type" class="form-select app-form-control @error('type') is-invalid @enderror" name="type" required>
                        @foreach ($types as $typeOption)
                            <option value="{{ $typeOption->value }}" @selected(old('type', $subject->type?->value) === $typeOption->value)>{{ ucfirst($typeOption->value) }}</option>
                        @endforeach
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('school.subjects.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save subject' : 'Create subject' }}</button>
        </div>
    </form>
@endsection
