@php
    $isEditing = $student->exists;
    $oldGuardians = old('guardians');
    $guardianRows = is_array($oldGuardians) ? array_pad($oldGuardians, count($guardians), []) : $guardians;
    $selectedSectionId = (int) old('section_id', $enrollment?->section_id);
@endphp

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Student' : 'Add Student', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">STUDENTS</span>
            <h1>{{ $isEditing ? $student->fullName() : 'Add a student' }}</h1>
            <p>Only the first name is required. Everything else can be completed later.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.students.index') }}">Back to students</a>
    </div>

    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert"><strong>Student was not saved.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.students.update', $student) : route('school.students.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="dashboard-panel h-100">
                    <div class="account-section-heading">
                        <span class="account-section-icon">S</span>
                        <div><h2>Student details</h2><p>Personal and contact information.</p></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label app-form-label" for="first_name">First name <span class="text-danger">*</span></label>
                            <input id="first_name" class="form-control app-form-control @error('first_name') is-invalid @enderror" name="first_name" value="{{ old('first_name', $student->first_name) }}" maxlength="100" required autofocus>
                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label app-form-label" for="last_name">Last name</label>
                            <input id="last_name" class="form-control app-form-control @error('last_name') is-invalid @enderror" name="last_name" value="{{ old('last_name', $student->last_name) }}" maxlength="100">
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label app-form-label" for="gender">Gender</label>
                            <select id="gender" class="form-select app-form-control @error('gender') is-invalid @enderror" name="gender">
                                <option value="">Not specified</option>
                                @foreach ($genders as $genderOption)
                                    <option value="{{ $genderOption->value }}" @selected(old('gender', $student->gender?->value) === $genderOption->value)>{{ ucfirst($genderOption->value) }}</option>
                                @endforeach
                            </select>
                            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label app-form-label" for="date_of_birth">Date of birth</label>
                            <input id="date_of_birth" class="form-control app-form-control @error('date_of_birth') is-invalid @enderror" name="date_of_birth" type="date" value="{{ old('date_of_birth', $student->date_of_birth?->toDateString()) }}">
                            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label app-form-label" for="blood_group">Blood group</label>
                            <input id="blood_group" class="form-control app-form-control text-uppercase @error('blood_group') is-invalid @enderror" name="blood_group" value="{{ old('blood_group', $student->blood_group) }}" maxlength="5" placeholder="B+">
                            @error('blood_group')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label app-form-label" for="phone">Phone</label>
                            <input id="phone" class="form-control app-form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $student->phone) }}" maxlength="30">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label app-form-label" for="email">Email</label>
                            <input id="email" class="form-control app-form-control @error('email') is-invalid @enderror" name="email" type="email" value="{{ old('email', $student->email) }}" maxlength="255">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label app-form-label" for="address">Address</label>
                            <textarea id="address" class="form-control app-form-control school-address @error('address') is-invalid @enderror" name="address" rows="2" maxlength="2000">{{ old('address', $student->address) }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="dashboard-panel h-100">
                    <div class="account-section-heading">
                        <span class="account-section-icon secure">A</span>
                        <div><h2>Admission</h2><p>Leave the number empty to generate it.</p></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="admission_no">Admission number</label>
                        <input id="admission_no" class="form-control app-form-control text-uppercase @error('admission_no') is-invalid @enderror" name="admission_no" value="{{ old('admission_no', $student->admission_no) }}" maxlength="50" placeholder="ADM-{{ now()->year }}-0001 (automatic)">
                        @error('admission_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label app-form-label" for="admission_date">Admission date</label>
                        <input id="admission_date" class="form-control app-form-control @error('admission_date') is-invalid @enderror" name="admission_date" type="date" value="{{ old('admission_date', $student->admission_date?->toDateString()) }}">
                        @error('admission_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @if ($currentSession)
                        <div class="mb-3">
                            <label class="form-label app-form-label" for="section_id">Class &amp; section · {{ $currentSession->name }}</label>
                            <select id="section_id" class="form-select app-form-control @error('section_id') is-invalid @enderror" name="section_id">
                                <option value="">Not placed yet</option>
                                @foreach ($sections->groupBy('class_name') as $className => $classSections)
                                    <optgroup label="{{ $className }}">
                                        @foreach ($classSections as $sectionOption)
                                            <option value="{{ $sectionOption->id }}" @selected($selectedSectionId === $sectionOption->id)>{{ $className }} – {{ $sectionOption->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label app-form-label" for="roll_no">Roll number</label>
                            <input id="roll_no" class="form-control app-form-control @error('roll_no') is-invalid @enderror" name="roll_no" value="{{ old('roll_no', $enrollment?->roll_no) }}" maxlength="20">
                            @error('roll_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <div class="member-global-warning mb-3">No current academic session is set, so the student cannot be placed in a section yet.</div>
                    @endif

                    <div>
                        <label class="form-label app-form-label" for="status">Status</label>
                        <select id="status" class="form-select app-form-control @error('status') is-invalid @enderror" name="status">
                            @foreach ($statuses as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected(old('status', $student->status?->value) === $statusOption->value)>{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                        <div class="form-text account-form-help">Students are never deleted; use Left or Passed out.</div>
                    </div>
                </section>
            </div>

            <div class="col-12">
                <section class="dashboard-panel">
                    <div class="account-section-heading">
                        <span class="account-section-icon">G</span>
                        <div><h2>Guardians</h2><p>Optional. A guardian with the same phone number is reused, so siblings share one record.</p></div>
                    </div>

                    <div class="row g-4">
                        @foreach ($guardianRows as $index => $guardianRow)
                            <div class="col-lg-6">
                                <div class="student-guardian-card">
                                    <span class="school-menu-group-name">{{ $index === 0 ? 'Primary guardian' : 'Second guardian' }}</span>
                                    <div class="row g-2">
                                        <div class="col-md-7">
                                            <label class="form-label app-form-label" for="guardian-{{ $index }}-name">Name</label>
                                            <input id="guardian-{{ $index }}-name" class="form-control app-form-control @error('guardians.'.$index.'.name') is-invalid @enderror" name="guardians[{{ $index }}][name]" value="{{ $guardianRow['name'] ?? '' }}" maxlength="255">
                                            @error('guardians.'.$index.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label app-form-label" for="guardian-{{ $index }}-relation">Relation</label>
                                            <select id="guardian-{{ $index }}-relation" class="form-select app-form-control" name="guardians[{{ $index }}][relation]">
                                                <option value="">—</option>
                                                @foreach ($relations as $relation)
                                                    <option value="{{ $relation->value }}" @selected(($guardianRow['relation'] ?? null) === $relation->value)>{{ ucfirst($relation->value) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label app-form-label" for="guardian-{{ $index }}-phone">Phone</label>
                                            <input id="guardian-{{ $index }}-phone" class="form-control app-form-control" name="guardians[{{ $index }}][phone]" value="{{ $guardianRow['phone'] ?? '' }}" maxlength="30">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label app-form-label" for="guardian-{{ $index }}-email">Email</label>
                                            <input id="guardian-{{ $index }}-email" class="form-control app-form-control @error('guardians.'.$index.'.email') is-invalid @enderror" name="guardians[{{ $index }}][email]" type="email" value="{{ $guardianRow['email'] ?? '' }}" maxlength="255">
                                            @error('guardians.'.$index.'.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('school.students.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save student' : 'Add student' }}</button>
        </div>
    </form>
@endsection
