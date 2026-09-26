@extends('layouts.dashboard', ['title' => 'Notices', 'panelLabel' => 'SCHOOL WORKSPACE', 'workspaceName' => $school->name])

@section('content')
    @php
        $stateLabels = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'live' => 'Live', 'expired' => 'Expired', 'archived' => 'Archived'];
        $stateClasses = ['draft' => 'school-status-trial', 'scheduled' => 'school-status-trial', 'live' => 'school-status-active', 'expired' => 'school-status-suspended', 'archived' => 'school-status-cancelled'];
        $canEdit = auth()->user()?->can('menu', ['notices', 'edit']) ?? false;
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">COMMUNICATION</span>
            <h1>Notices</h1>
            <p>{{ $manage ? 'Every notice of the school: drafts, scheduled, live, expired and archived.' : 'Notices for you. Pinned notices stay on top.' }}</p>
        </div>
        @can('menu', ['notices', 'create'])
            <a class="btn btn-primary app-btn-primary" href="{{ route('school.notices.create') }}">+ New notice</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif

    @if ($canManage)
        <nav class="attendance-report-tabs" aria-label="Notice views">
            <a class="{{ $manage ? '' : 'active' }}" href="{{ route('school.notices.index') }}">Notice board</a>
            <a class="{{ $manage ? 'active' : '' }}" href="{{ route('school.notices.index', ['view' => 'manage']) }}">Manage</a>
        </nav>
    @endif

    <section class="dashboard-panel mt-3">
        <form class="school-filter-bar" method="GET" action="{{ route('school.notices.index') }}">
            @if ($manage)<input type="hidden" name="view" value="manage">@endif
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search notices</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Title or text">
            </div>
            @if ($manage)
                <div>
                    <label class="visually-hidden" for="state">State</label>
                    <select id="state" class="form-select app-form-control" name="state">
                        <option value="">All notices</option>
                        @foreach ($stateLabels as $stateValue => $stateLabel)
                            <option value="{{ $stateValue }}" @selected($selectedState === $stateValue)>{{ $stateLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button class="btn school-filter-button" type="submit">Search</button>
            @if ($search !== '' || $selectedState !== '')<a class="btn school-clear-button" href="{{ route('school.notices.index', $manage ? ['view' => 'manage'] : []) }}">Clear</a>@endif
        </form>

        @if ($notices->isEmpty())
            <div class="school-list-empty">
                <span>N</span>
                <h2>No notices</h2>
                <p>{{ $search !== '' || $selectedState !== '' ? 'Try changing your search.' : ($manage ? 'Write the first notice for your school.' : 'There is nothing on the notice board for you right now.') }}</p>
            </div>
        @elseif ($manage)
            <div class="table-responsive school-table-wrap">
                <table class="table school-table align-middle mb-0">
                    <thead><tr><th>Notice</th><th>For</th><th>State</th><th class="text-center">Read by</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($notices as $noticeItem)
                            @php($state = $noticeItem->state($today))
                            <tr>
                                <td>
                                    <div class="notice-title-cell">
                                        <button class="notice-title-button" type="button" data-notice-preview="{{ route('school.notices.preview', $noticeItem) }}"><strong>{{ $noticeItem->title }}</strong></button>
                                        @if ($noticeItem->is_pinned)<span class="notice-pin" title="Pinned">📌 Pinned</span>@endif
                                        <small>
                                            {{ $noticeItem->creator?->name ?? '—' }} ·
                                            @if ($state === 'scheduled')
                                                publishes {{ $noticeItem->publish_at->setTimezone($school->timezone)->format('d M, h:i A') }}
                                            @else
                                                {{ ($noticeItem->publish_at ?? $noticeItem->created_at)->setTimezone($school->timezone)->format('d M Y') }}
                                            @endif
                                            @if ($noticeItem->expires_on) · until {{ $noticeItem->expires_on->format('d M Y') }}@endif
                                        </small>
                                    </div>
                                </td>
                                <td class="notice-audience-cell">@include('school.notices._audience', ['notice' => $noticeItem])</td>
                                <td><span class="school-status {{ $stateClasses[$state] }}">{{ $stateLabels[$state] }}</span></td>
                                <td class="text-center"><span class="academic-count-pill">{{ $noticeItem->reads_count }}</span></td>
                                <td class="text-end">
                                    @if ($canEdit)
                                        <div class="school-row-actions">
                                            @if ($state === 'archived')
                                                <form method="POST" action="{{ route('school.notices.restore', $noticeItem) }}">
                                                    @csrf @method('PATCH')
                                                    <button class="btn school-row-action" type="submit">Restore</button>
                                                </form>
                                            @else
                                                <a class="btn school-row-action" href="{{ route('school.notices.edit', $noticeItem) }}">Edit</a>
                                                <form method="POST" action="{{ route('school.notices.archive', $noticeItem) }}" data-confirm="Archive “{{ $noticeItem->title }}”? Nobody will see it any more.">
                                                    @csrf @method('PATCH')
                                                    <button class="btn school-row-action school-row-action-danger" type="submit">Archive</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="school-pagination">{{ $notices->links() }}</div>
        @else
            <div class="notice-board">
                @foreach ($notices as $noticeItem)
                    <button class="notice-card {{ $noticeItem->is_pinned ? 'is-pinned' : '' }} {{ $noticeItem->read_by_me ? '' : 'is-unread' }}" type="button" data-notice-preview="{{ route('school.notices.preview', $noticeItem) }}">
                        <span class="notice-card-head">
                            @if ($noticeItem->is_pinned)<span class="notice-pin">📌 Pinned</span>@endif
                            @unless ($noticeItem->read_by_me)<span class="notice-new">New</span>@endunless
                            <span class="notice-date">{{ ($noticeItem->publish_at ?? $noticeItem->created_at)->setTimezone($school->timezone)->format('d M Y') }}</span>
                        </span>
                        <strong>{{ $noticeItem->title }}</strong>
                        @if ($noticeItem->body)<span class="notice-card-text">{{ \Illuminate\Support\Str::limit($noticeItem->body, 180) }}</span>@endif
                        <small>{{ $noticeItem->creator?->name ?? '—' }}@if ($noticeItem->expires_on) · until {{ $noticeItem->expires_on->format('d M Y') }}@endif</small>
                    </button>
                @endforeach
            </div>
            <div class="school-pagination">{{ $notices->links() }}</div>
        @endif
    </section>
@endsection
