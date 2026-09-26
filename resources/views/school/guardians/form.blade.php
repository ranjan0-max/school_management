@extends('layouts.dashboard', ['title' => 'Edit Guardian', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">GUARDIANS</span>
            <h1>{{ $guardian->name }}</h1>
            <p>Only the name is required. Guardians are added and linked from the student's form.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.guardians.index') }}">Back to guardians</a>
    </div>

    <form class="mt-4" method="POST" action="{{ route('school.guardians.update', $guardian) }}">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="dashboard-panel h-100">
                    <div class="account-section-heading">
                        <span class="account-section-icon">G</span>
                        <div><h2>Guardian details</h2><p>Phone is used to recognise the same guardian for siblings.</p></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label app-form-label" for="name">Full name <span class="text-danger">*</span></label>
                            <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $guardian->name) }}" maxlength="255" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label app-form-label" for="occupation">Occupation</label>
                            <input id="occupation" class="form-control app-form-control @error('occupation') is-invalid @enderror" name="occupation" value="{{ old('occupation', $guardian->occupation) }}" maxlength="100">
                            @error('occupation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label app-form-label" for="phone">Phone</label>
                            <input id="phone" class="form-control app-form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $guardian->phone) }}" maxlength="30">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label app-form-label" for="email">Email</label>
                            <input id="email" class="form-control app-form-control @error('email') is-invalid @enderror" name="email" type="email" value="{{ old('email', $guardian->email) }}" maxlength="255">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label app-form-label" for="address">Address</label>
                            <textarea id="address" class="form-control app-form-control school-address @error('address') is-invalid @enderror" name="address" rows="2" maxlength="2000">{{ old('address', $guardian->address) }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="dashboard-panel h-100">
                    <div class="account-section-heading">
                        <span class="account-section-icon secure">S</span>
                        <div><h2>Linked students</h2><p>{{ $students->count() }} student(s)</p></div>
                    </div>
                    @forelse ($students as $student)
                        <div class="school-table-identity mb-2">
                            <span>{{ strtoupper(substr($student->first_name, 0, 1)) }}</span>
                            <div>
                                <strong>
                                    @can('menu', ['students', 'edit'])
                                        <a href="{{ route('school.students.edit', $student) }}">{{ $student->fullName() }}</a>
                                    @else
                                        {{ $student->fullName() }}
                                    @endcan
                                </strong>
                                <small>{{ ucfirst($student->pivot->relation ?? 'guardian') }}{{ $student->pivot->is_primary ? ' · primary contact' : '' }} · {{ $student->admission_no }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No students linked yet.</p>
                    @endforelse
                </section>
            </div>
        </div>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('school.guardians.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">Save guardian</button>
        </div>
    </form>
@endsection
