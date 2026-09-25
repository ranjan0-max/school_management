@php
    $isEditing = $employee->exists;
    $route = $type->routePrefix();
@endphp

@extends('layouts.dashboard', ['title' => ($isEditing ? 'Edit ' : 'Add ').$type->label(), 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">{{ strtoupper($type->pluralLabel()) }}</span>
            <h1>{{ $isEditing ? $employee->name : 'Add '.strtolower($type->label()) }}</h1>
            <p>Only the name is required. The rest can be filled in later.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route($route.'.index') }}">Back to {{ strtolower($type->pluralLabel()) }}</a>
    </div>

    <form class="mt-4" method="POST" action="{{ $isEditing ? route($route.'.update', $employee) : route($route.'.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="dashboard-panel h-100">
                    <div class="account-section-heading">
                        <span class="account-section-icon">{{ substr($type->label(), 0, 1) }}</span>
                        <div><h2>Personal details</h2><p>Basic information and contact details.</p></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label app-form-label" for="name">Full name <span class="text-danger">*</span></label>
                            <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $employee->name) }}" maxlength="255" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label app-form-label" for="gender">Gender</label>
                            <select id="gender" class="form-select app-form-control @error('gender') is-invalid @enderror" name="gender">
                                <option value="">Not specified</option>
                                @foreach ($genders as $genderOption)
                                    <option value="{{ $genderOption->value }}" @selected(old('gender', $employee->gender?->value) === $genderOption->value)>{{ ucfirst($genderOption->value) }}</option>
                                @endforeach
                            </select>
                            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label app-form-label" for="date_of_birth">Date of birth</label>
                            <input id="date_of_birth" class="form-control app-form-control @error('date_of_birth') is-invalid @enderror" name="date_of_birth" type="date" value="{{ old('date_of_birth', $employee->date_of_birth?->toDateString()) }}">
                            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label app-form-label" for="phone">Phone</label>
                            <input id="phone" class="form-control app-form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $employee->phone) }}" maxlength="30">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label app-form-label" for="email">Email</label>
                            <input id="email" class="form-control app-form-control @error('email') is-invalid @enderror" name="email" type="email" value="{{ old('email', $employee->email) }}" maxlength="255">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label app-form-label" for="address">Address</label>
                            <textarea id="address" class="form-control app-form-control school-address @error('address') is-invalid @enderror" name="address" rows="2" maxlength="2000">{{ old('address', $employee->address) }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="dashboard-panel h-100">
                    <div class="account-section-heading">
                        <span class="account-section-icon secure">J</span>
                        <div><h2>Job details</h2><p>Leave the number empty to generate it.</p></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="employee_no">Employee number</label>
                        <input id="employee_no" class="form-control app-form-control text-uppercase @error('employee_no') is-invalid @enderror" name="employee_no" value="{{ old('employee_no', $employee->employee_no) }}" maxlength="50" placeholder="{{ $type->numberPrefix() }}-0001 (automatic)">
                        @error('employee_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label app-form-label" for="designation">Designation</label>
                        <input id="designation" class="form-control app-form-control @error('designation') is-invalid @enderror" name="designation" value="{{ old('designation', $employee->designation) }}" maxlength="100" placeholder="{{ $type === \App\Enums\EmployeeType::Teacher ? 'PGT Maths' : 'Accountant' }}">
                        @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label app-form-label" for="qualification">Qualification</label>
                        <input id="qualification" class="form-control app-form-control @error('qualification') is-invalid @enderror" name="qualification" value="{{ old('qualification', $employee->qualification) }}" maxlength="255" placeholder="M.Sc, B.Ed">
                        @error('qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label app-form-label" for="joining_date">Joining date</label>
                        <input id="joining_date" class="form-control app-form-control @error('joining_date') is-invalid @enderror" name="joining_date" type="date" value="{{ old('joining_date', $employee->joining_date?->toDateString()) }}">
                        @error('joining_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label app-form-label" for="status">Status</label>
                        <select id="status" class="form-select app-form-control @error('status') is-invalid @enderror" name="status">
                            @foreach ($statuses as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected(old('status', $employee->status?->value) === $statusOption->value)>{{ ucfirst($statusOption->value) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text account-form-help">Records are never deleted; mark people who leave as “Left”.</div>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>
        </div>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route($route.'.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save changes' : 'Add '.strtolower($type->label()) }}</button>
        </div>
    </form>
@endsection
