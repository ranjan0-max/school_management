@php($isEditing = $section->exists)

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Section' : 'Add Section', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">SECTIONS</span>
            <h1>{{ $isEditing ? 'Edit section '.$section->name : 'Add a section' }}</h1>
            <p>Choose the class and give the section a short name such as A or Rose.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.sections.index') }}">Back to sections</a>
    </div>

    @if ($classes->isEmpty())
        <div class="role-permission-empty mt-4">
            <strong>No classes yet</strong>
            <p>A section belongs to a class. Add a class first.</p>
            @can('menu', ['classes', 'create'])
                <a class="btn school-access-button" href="{{ route('school.classes.create') }}">Add a class</a>
            @endcan
        </div>
    @else
        <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.sections.update', $section) : route('school.sections.store') }}">
            @csrf
            @if ($isEditing) @method('PUT') @endif

            <section class="dashboard-panel">
                <div class="account-section-heading">
                    <span class="account-section-icon">S</span>
                    <div><h2>Section details</h2><p>Section names must be unique within a class.</p></div>
                </div>

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label app-form-label" for="class_id">Class <span class="text-danger">*</span></label>
                        <select id="class_id" class="form-select app-form-control @error('class_id') is-invalid @enderror" name="class_id" required>
                            <option value="">Select a class…</option>
                            @foreach ($classes as $classOption)
                                <option value="{{ $classOption->id }}" @selected((int) old('class_id', $section->class_id) === $classOption->id)>{{ $classOption->name }}</option>
                            @endforeach
                        </select>
                        @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label app-form-label" for="name">Section name <span class="text-danger">*</span></label>
                        <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $section->name) }}" maxlength="50" placeholder="A" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label app-form-label" for="capacity">Capacity</label>
                        <input id="capacity" class="form-control app-form-control @error('capacity') is-invalid @enderror" name="capacity" type="number" min="1" max="65535" value="{{ old('capacity', $section->capacity) }}" placeholder="Optional">
                        @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            <div class="school-form-actions">
                <a class="btn school-secondary-button" href="{{ route('school.sections.index') }}">Cancel</a>
                <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save section' : 'Create section' }}</button>
            </div>
        </form>
    @endif
@endsection
