@php
    $isEditing = $schoolClass->exists;
    $oldSubjects = old('subject_ids');
    $checkedSubjectIds = is_array($oldSubjects) ? array_map('intval', $oldSubjects) : $selectedSubjectIds;
@endphp

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Class' : 'Add Class', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">CLASSES</span>
            <h1>{{ $isEditing ? 'Edit '.$schoolClass->name : 'Add a class' }}</h1>
            <p>Name the class, set its display order, and pick the subjects it studies.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.classes.index') }}">Back to classes</a>
    </div>

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.classes.update', $schoolClass) : route('school.classes.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-4">
                <section class="dashboard-panel h-100">
                    <div class="account-section-heading">
                        <span class="account-section-icon">C</span>
                        <div><h2>Class details</h2><p>Lower display order appears first.</p></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="name">Class name <span class="text-danger">*</span></label>
                        <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $schoolClass->name) }}" maxlength="100" placeholder="Class 5" required autofocus>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="form-label app-form-label" for="sort_order">Display order</label>
                        <input id="sort_order" class="form-control app-form-control @error('sort_order') is-invalid @enderror" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $schoolClass->sort_order) }}">
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>

            <div class="col-xl-8">
                <section class="dashboard-panel h-100" data-check-scope>
                    <div class="dashboard-panel-heading">
                        <div><span class="section-kicker">CURRICULUM</span><h2>Subjects taught in this class</h2></div>
                        @if ($subjects->isNotEmpty())
                            <label class="check-all-toggle"><input class="form-check-input" type="checkbox" data-check-all><span>Select all</span></label>
                        @endif
                    </div>

                    @error('subject_ids')<div class="alert school-access-error mt-3" role="alert"><span>{{ $message }}</span></div>@enderror

                    @if ($subjects->isEmpty())
                        <div class="role-permission-empty mt-3">
                            <strong>No subjects yet</strong>
                            <p>Add subjects first; you can attach them to this class later.</p>
                            @can('menu', ['subjects', 'create'])
                                <a class="btn school-access-button" href="{{ route('school.subjects.create') }}">Add subjects</a>
                            @endcan
                        </div>
                    @else
                        <div class="academic-subject-options mt-3">
                            @foreach ($subjects as $subject)
                                <label class="school-menu-choice" for="subject-{{ $subject->id }}">
                                    <input id="subject-{{ $subject->id }}" class="form-check-input" name="subject_ids[]" type="checkbox" value="{{ $subject->id }}" @checked(in_array((int) $subject->id, $checkedSubjectIds, true))>
                                    <span>{{ $subject->name }}@if ($subject->code) <small class="text-muted">· {{ $subject->code }}</small>@endif</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </div>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('school.classes.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save class' : 'Create class' }}</button>
        </div>
    </form>
@endsection
