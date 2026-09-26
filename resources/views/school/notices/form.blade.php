@php
    $isEditing = $notice->exists;
    $currentMode = match (true) {
        $notice->status !== \App\Enums\NoticeStatus::Published => 'draft',
        $notice->publish_at !== null && $notice->publish_at->isFuture() => 'schedule',
        default => 'now',
    };
    $mode = old('publish_mode', $isEditing ? $currentMode : 'now');
    $audience = old('audience', $notice->audience ?? \App\Models\Notice::AUDIENCE_SCHOOL);
    $selectedRoles = array_map('intval', (array) old('role_ids', $isEditing ? $notice->targetIds(\App\Models\Notice::TARGET_ROLE) : []));
    $selectedClasses = array_map('intval', (array) old('class_ids', $isEditing ? $notice->targetIds(\App\Models\Notice::TARGET_CLASS) : []));
    $publishAt = old('publish_at', $notice->publish_at?->setTimezone($timezone)->format('Y-m-d\TH:i'));
@endphp

@extends('layouts.dashboard', ['title' => $isEditing ? 'Edit Notice' : 'New Notice', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">NOTICES</span>
            <h1>{{ $isEditing ? 'Edit notice' : 'New notice' }}</h1>
            <p>Only the title is required. Choose who sees it, and optionally pin it, schedule it or set an expiry date.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('school.notices.index', ['view' => 'manage']) }}">Back to notices</a>
    </div>

    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert"><strong>Notice was not saved.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <form class="mt-4" method="POST" action="{{ $isEditing ? route('school.notices.update', $notice) : route('school.notices.store') }}" data-notice-form>
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-7">
                <section class="dashboard-panel h-100">
                    <div class="account-section-heading">
                        <span class="account-section-icon">N</span>
                        <div><h2>Notice</h2><p>Line breaks are kept as you type them.</p></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="title">Title <span class="text-danger">*</span></label>
                        <input id="title" class="form-control app-form-control @error('title') is-invalid @enderror" name="title" value="{{ old('title', $notice->title) }}" maxlength="200" required autofocus placeholder="School closed on Monday">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="body">Details</label>
                        <textarea id="body" class="form-control app-form-control @error('body') is-invalid @enderror" name="body" rows="10" maxlength="20000">{{ old('body', $notice->body) }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <input name="is_pinned" type="hidden" value="0">
                    <label class="role-active-choice" for="is_pinned">
                        <input id="is_pinned" class="form-check-input" name="is_pinned" type="checkbox" value="1" @checked((bool) old('is_pinned', $notice->is_pinned))>
                        <span><strong>📌 Pin to the top</strong><small>Pinned notices stay above all others on everyone's notice board until unpinned, expired or archived.</small></span>
                    </label>
                </section>
            </div>

            <div class="col-xl-5">
                <section class="dashboard-panel mb-4">
                    <div class="account-section-heading">
                        <span class="account-section-icon secure">W</span>
                        <div><h2>Who sees it</h2><p>Pick the whole school, or any mix of roles and classes.</p></div>
                    </div>

                    <div class="notice-choice-list">
                        <label class="notice-choice">
                            <input class="form-check-input" type="radio" name="audience" value="{{ \App\Models\Notice::AUDIENCE_SCHOOL }}" data-audience-choice @checked($audience === \App\Models\Notice::AUDIENCE_SCHOOL)>
                            <span><strong>Whole school</strong><small>All staff, and parents once parent login is available.</small></span>
                        </label>
                        <label class="notice-choice">
                            <input class="form-check-input" type="radio" name="audience" value="{{ \App\Models\Notice::AUDIENCE_SELECTED }}" data-audience-choice @checked($audience === \App\Models\Notice::AUDIENCE_SELECTED)>
                            <span><strong>Selected roles and classes</strong><small>Tick one or more below.</small></span>
                        </label>
                    </div>
                    @error('audience')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

                    <div class="notice-targets" data-audience-targets>
                        <fieldset class="mt-3">
                            <legend class="form-label app-form-label">Roles (staff)</legend>
                            @forelse ($roles as $roleOption)
                                <label class="notice-target">
                                    <input class="form-check-input" type="checkbox" name="role_ids[]" value="{{ $roleOption->id }}" @checked(in_array($roleOption->id, $selectedRoles, true))>
                                    <span>{{ $roleOption->name }}@if ($roleOption->isPlatform())<small> · platform</small>@endif</span>
                                </label>
                            @empty
                                <p class="text-muted small mb-0">No roles yet.</p>
                            @endforelse
                        </fieldset>

                        <fieldset class="mt-3">
                            <legend class="form-label app-form-label">Parents of class</legend>
                            @forelse ($classes as $classOption)
                                <label class="notice-target">
                                    <input class="form-check-input" type="checkbox" name="class_ids[]" value="{{ $classOption->id }}" @checked(in_array($classOption->id, $selectedClasses, true))>
                                    <span>{{ $classOption->name }}</span>
                                </label>
                            @empty
                                <p class="text-muted small mb-0">No classes yet.</p>
                            @endforelse
                            <div class="form-text account-form-help">Parents will see these notices once parent login is available.</div>
                        </fieldset>
                    </div>
                </section>

                <section class="dashboard-panel">
                    <div class="account-section-heading">
                        <span class="account-section-icon">T</span>
                        <div><h2>When</h2><p>Times are in {{ $timezone }}.</p></div>
                    </div>

                    <div class="notice-choice-list">
                        <label class="notice-choice">
                            <input class="form-check-input" type="radio" name="publish_mode" value="now" data-publish-choice @checked($mode === 'now')>
                            <span><strong>Publish now</strong></span>
                        </label>
                        <label class="notice-choice">
                            <input class="form-check-input" type="radio" name="publish_mode" value="schedule" data-publish-choice @checked($mode === 'schedule')>
                            <span><strong>Schedule</strong><small>Appears automatically at the chosen time.</small></span>
                        </label>
                        <label class="notice-choice">
                            <input class="form-check-input" type="radio" name="publish_mode" value="draft" data-publish-choice @checked($mode === 'draft')>
                            <span><strong>Save as draft</strong><small>Nobody sees it until you publish.</small></span>
                        </label>
                    </div>

                    <div class="mt-3" data-publish-at>
                        <label class="form-label app-form-label" for="publish_at">Publish at</label>
                        <input id="publish_at" class="form-control app-form-control @error('publish_at') is-invalid @enderror" name="publish_at" type="datetime-local" value="{{ $publishAt }}">
                        @error('publish_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mt-3">
                        <label class="form-label app-form-label" for="expires_on">Show until</label>
                        <input id="expires_on" class="form-control app-form-control @error('expires_on') is-invalid @enderror" name="expires_on" type="date" value="{{ old('expires_on', $notice->expires_on?->toDateString()) }}">
                        <div class="form-text account-form-help">Optional. The notice disappears after this day.</div>
                        @error('expires_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>
        </div>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('school.notices.index', ['view' => 'manage']) }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">Save notice</button>
        </div>
    </form>
@endsection
