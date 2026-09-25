@php($isEditing = $school->exists)

<div class="row g-4">
    <div class="col-xl-8">
        <section class="dashboard-panel h-100">
            <div class="account-section-heading">
                <span class="account-section-icon">S</span>
                <div>
                    <h2>School identity</h2>
                    <p>Only the school name and status are mandatory. Other details can be completed later.</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label app-form-label" for="name">School name <span class="text-danger">*</span></label>
                    <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $school->name) }}" maxlength="255" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label app-form-label" for="code">School code</label>
                    <input id="code" class="form-control app-form-control text-uppercase @error('code') is-invalid @enderror" name="code" value="{{ old('code', $school->code) }}" maxlength="50" placeholder="SCH-001">
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label app-form-label" for="slug">URL slug</label>
                    <input id="slug" class="form-control app-form-control @error('slug') is-invalid @enderror" name="slug" value="{{ old('slug', $school->slug) }}" maxlength="255" placeholder="Automatically generated">
                    <div class="form-text account-form-help">Leave empty to generate it from the school name.</div>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label app-form-label" for="status">Status <span class="text-danger">*</span></label>
                    <select id="status" class="form-select app-form-control @error('status') is-invalid @enderror" name="status" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $school->status?->value ?? $school->status) === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <textarea id="address" class="form-control app-form-control school-address @error('address') is-invalid @enderror" name="address" maxlength="5000" rows="4">{{ old('address', $school->address) }}</textarea>
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="dashboard-panel mb-4">
            <div class="account-section-heading">
                <span class="account-section-icon secure">L</span>
                <div><h2>Locale settings</h2><p>Defaults used across this school.</p></div>
            </div>
            <div class="mb-3">
                <label class="form-label app-form-label" for="timezone">Timezone</label>
                <input id="timezone" class="form-control app-form-control @error('timezone') is-invalid @enderror" name="timezone" value="{{ old('timezone', $school->timezone ?? 'Asia/Kolkata') }}" required>
                @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label app-form-label" for="locale">Locale</label>
                    <input id="locale" class="form-control app-form-control @error('locale') is-invalid @enderror" name="locale" value="{{ old('locale', $school->locale ?? 'en') }}" maxlength="10" required>
                    @error('locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label class="form-label app-form-label" for="currency">Currency</label>
                    <input id="currency" class="form-control app-form-control text-uppercase @error('currency') is-invalid @enderror" name="currency" value="{{ old('currency', $school->currency ?? 'INR') }}" maxlength="3" required>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="dashboard-panel">
            <div class="account-section-heading">
                <span class="account-section-icon billing">B</span>
                <div><h2>Plan limits</h2><p>Optional SaaS and trial controls.</p></div>
            </div>
            <div class="mb-3">
                <label class="form-label app-form-label" for="trial_ends_at">Trial ends</label>
                <input id="trial_ends_at" class="form-control app-form-control @error('trial_ends_at') is-invalid @enderror" name="trial_ends_at" type="datetime-local" value="{{ old('trial_ends_at', $school->trial_ends_at?->format('Y-m-d\TH:i')) }}">
                @error('trial_ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label app-form-label" for="max_students">Student limit</label>
                    <input id="max_students" class="form-control app-form-control @error('max_students') is-invalid @enderror" name="max_students" type="number" min="0" value="{{ old('max_students', $school->max_students) }}">
                    @error('max_students')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label class="form-label app-form-label" for="max_staff">Staff limit</label>
                    <input id="max_staff" class="form-control app-form-control @error('max_staff') is-invalid @enderror" name="max_staff" type="number" min="0" value="{{ old('max_staff', $school->max_staff) }}">
                    @error('max_staff')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row g-3">
                <div class="col-7">
                    <label class="form-label app-form-label" for="price_per_student">Price / student</label>
                    <input id="price_per_student" class="form-control app-form-control @error('price_per_student') is-invalid @enderror" name="price_per_student" type="number" min="0" step="0.01" value="{{ old('price_per_student', $school->price_per_student) }}">
                    @error('price_per_student')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-5">
                    <label class="form-label app-form-label" for="billing_cycle">Cycle</label>
                    <select id="billing_cycle" class="form-select app-form-control @error('billing_cycle') is-invalid @enderror" name="billing_cycle">
                        <option value="">None</option>
                        @foreach (['monthly', 'quarterly', 'yearly'] as $cycle)
                            <option value="{{ $cycle }}" @selected(old('billing_cycle', $school->billing_cycle) === $cycle)>{{ ucfirst($cycle) }}</option>
                        @endforeach
                    </select>
                    @error('billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>
    </div>
</div>

<div class="school-form-actions">
    <a class="btn school-secondary-button" href="{{ route('platform.schools.index') }}">Cancel</a>
    <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save changes' : 'Create school' }}</button>
</div>
