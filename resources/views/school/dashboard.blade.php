@extends('layouts.dashboard', [
    'title' => 'School Dashboard',
    'panelLabel' => 'SCHOOL WORKSPACE',
    'workspaceName' => $school->name,
])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">{{ strtoupper($school->code ?: 'SCHOOL OVERVIEW') }}</span>
            <h1>Welcome, {{ auth()->user()?->name ?? 'Team Member' }}.</h1>
            <p>{{ $school->name }} · Your assigned modules and role-based navigation will appear here.</p>
        </div>
        <span class="dashboard-date">{{ now()->format('l, d M Y') }}</span>
    </div>

    @if ($notices !== null)
        <section class="dashboard-panel mt-4">
            <div class="dashboard-panel-heading">
                <div><span class="section-kicker">COMMUNICATION</span><h2>Notice board</h2></div>
                <a class="btn school-secondary-button btn-sm" href="{{ route('school.notices.index') }}">All notices</a>
            </div>
            @if ($notices->isEmpty())
                <p class="text-muted small mb-0">No notices for you right now.</p>
            @else
                <div class="dashboard-notice-list">
                    @foreach ($notices as $noticeItem)
                        <button class="notice-card {{ $noticeItem->is_pinned ? 'is-pinned' : '' }} {{ $noticeItem->read_by_me ? '' : 'is-unread' }}" type="button" data-notice-preview="{{ route('school.notices.preview', $noticeItem) }}">
                            <span class="notice-card-head">
                                @if ($noticeItem->is_pinned)<span class="notice-pin">📌 Pinned</span>@endif
                                @unless ($noticeItem->read_by_me)<span class="notice-new">New</span>@endunless
                                <span class="notice-date">{{ ($noticeItem->publish_at ?? $noticeItem->created_at)->setTimezone($school->timezone)->format('d M Y') }}</span>
                            </span>
                            <strong>{{ $noticeItem->title }}</strong>
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <section class="dashboard-empty-state mt-4">
        <span class="dashboard-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 20V8l8-4 8 4v12M8 20v-5h8v5M8 10h.01M12 10h.01M16 10h.01" /></svg>
        </span>
        <span class="section-kicker">WORKSPACE READY</span>
        <h2>School modules will appear here</h2>
        <p>Once the Super Admin assigns modules and menus, this dashboard will adapt automatically to your access.</p>
    </section>

    @if (auth()->user()?->isSuperAdmin())
        <form class="text-center mt-4" method="POST" action="{{ route('platform.schools.leave') }}">
            @csrf
            @method('DELETE')
            <button class="btn school-switch-button" type="submit">Return to Platform Control</button>
        </form>
    @endif
@endsection
