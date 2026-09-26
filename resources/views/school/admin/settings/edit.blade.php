@extends('layouts.dashboard', ['title' => 'School Settings', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php
        $canEdit = auth()->user()?->can('menu', ['school_settings', 'edit']) ?? false;
        $workingDays = array_map('intval', (array) old('working_days', $school->workingDays()));
        $value = fn (string $key) => old($key, $school->setting($key));
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ADMINISTRATION</span>
            <h1>School Settings</h1>
            <p>Your school's details and preferences. Only the name, timezone and working days are required.</p>
        </div>
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert"><strong>Settings were not saved.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <form class="mt-4" method="POST" action="{{ route('school.settings.update') }}">
        @csrf
        @method('PUT')

        <fieldset @disabled(! $canEdit)>
            <div class="row g-4">
                <div class="col-xl-7">
                    <section class="dashboard-panel h-100">
                        <div class="account-section-heading">
                            <span class="account-section-icon">S</span>
                            <div><h2>School details</h2><p>Shown on reports and, later, on report cards and receipts.</p></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label app-form-label" for="name">School name <span class="text-danger">*</span></label>
                                <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $school->name) }}" maxlength="255" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label app-form-label" for="email">Contact email</label>
                                <input id="email" class="form-control app-form-control @error('email') is-invalid @enderror" name="email" type="email" value="{{ old('email', $school->email) }}" maxlength="255">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label app-form-label" for="phone">Contact phone</label>
                                <input id="phone" class="form-control app-form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $school->phone) }}" maxlength="30">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label app-form-label" for="address">Address</label>
                                <textarea id="address" class="form-control app-form-control school-address @error('address') is-invalid @enderror" name="address" maxlength="5000" rows="3">{{ old('address', $school->address) }}</textarea>
                                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label app-form-label" for="principal_name">Principal</label>
                                <input id="principal_name" class="form-control app-form-control @error('principal_name') is-invalid @enderror" name="principal_name" value="{{ $value('principal_name') }}" maxlength="255">
                                @error('principal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label app-form-label" for="website">Website</label>
                                <input id="website" class="form-control app-form-control @error('website') is-invalid @enderror" name="website" type="url" value="{{ $value('website') }}" maxlength="255" placeholder="https://">
                                @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label app-form-label" for="board">Board</label>
                                <input id="board" class="form-control app-form-control @error('board') is-invalid @enderror" name="board" value="{{ $value('board') }}" maxlength="100" placeholder="CBSE, ICSE, State board…">
                                @error('board')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label app-form-label" for="affiliation_no">Affiliation no</label>
                                <input id="affiliation_no" class="form-control app-form-control @error('affiliation_no') is-invalid @enderror" name="affiliation_no" value="{{ $value('affiliation_no') }}" maxlength="100">
                                @error('affiliation_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label app-form-label" for="established_year">Established</label>
                                <input id="established_year" class="form-control app-form-control @error('established_year') is-invalid @enderror" name="established_year" type="number" min="1800" max="{{ now()->format('Y') }}" value="{{ $value('established_year') }}" placeholder="Year">
                                @error('established_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-xl-5">
                    <section class="dashboard-panel mb-4">
                        <div class="account-section-heading">
                            <span class="account-section-icon secure">P</span>
                            <div><h2>Preferences</h2><p>Used across attendance and admissions.</p></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label app-form-label" for="timezone">Timezone <span class="text-danger">*</span></label>
                            <select id="timezone" class="form-select app-form-control @error('timezone') is-invalid @enderror" name="timezone" required>
                                @foreach ($timezones as $timezoneOption)
                                    <option value="{{ $timezoneOption }}" @selected(old('timezone', $school->timezone) === $timezoneOption)>{{ $timezoneOption }}</option>
                                @endforeach
                            </select>
                            <div class="form-text account-form-help">Decides what "today" is for attendance.</div>
                            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label app-form-label" for="admission_no_prefix">Admission number prefix</label>
                            <input id="admission_no_prefix" class="form-control app-form-control text-uppercase @error('admission_no_prefix') is-invalid @enderror" name="admission_no_prefix" value="{{ $value('admission_no_prefix') }}" maxlength="10" placeholder="ADM">
                            <div class="form-text account-form-help">New students get numbers like {{ $school->admissionPrefix() }}-{{ now()->format('Y') }}-0001.</div>
                            @error('admission_no_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <fieldset>
                            <legend class="form-label app-form-label">Working days <span class="text-danger">*</span></legend>
                            <div class="settings-weekdays">
                                @foreach ($week as $dayNumber => $dayName)
                                    <input class="btn-check" type="checkbox" id="working_day_{{ $dayNumber }}" name="working_days[]" value="{{ $dayNumber }}" @checked(in_array($dayNumber, $workingDays, true))>
                                    <label class="settings-weekday" for="working_day_{{ $dayNumber }}" title="{{ $dayName }}">{{ substr($dayName, 0, 3) }}</label>
                                @endforeach
                            </div>
                            <div class="form-text account-form-help">Other days are shaded as weekly off in attendance reports.</div>
                            @error('working_days')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </fieldset>
                    </section>

                    <section class="dashboard-panel">
                        <div class="account-section-heading">
                            <span class="account-section-icon">i</span>
                            <div><h2>Managed by the platform</h2><p>Ask the platform administrator to change these.</p></div>
                        </div>
                        <dl class="settings-readonly">
                            <div><dt>School code</dt><dd>{{ $school->code ?: '—' }}</dd></div>
                            <div><dt>Plan status</dt><dd>{{ ucfirst($school->status->value) }}{{ $school->trial_ends_at ? ' · trial ends '.$school->trial_ends_at->format('d M Y') : '' }}</dd></div>
                            <div><dt>Currency</dt><dd>{{ $school->currency }}</dd></div>
                            <div><dt>Student limit</dt><dd>{{ $school->max_students ?? 'No limit' }}</dd></div>
                        </dl>
                    </section>
                </div>
            </div>
        </fieldset>

        @if ($canEdit)
            <div class="school-form-actions">
                <button class="btn btn-primary app-btn-primary" type="submit">Save settings</button>
            </div>
        @endif
    </form>
@endsection
