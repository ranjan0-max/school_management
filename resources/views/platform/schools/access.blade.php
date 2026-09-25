@extends('layouts.dashboard', ['title' => 'School Menus', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    @php
        $oldMenuValues = old('menu_ids', $assignedMenuIds);
        $checkedMenuIds = is_array($oldMenuValues) ? array_map('intval', $oldMenuValues) : $assignedMenuIds;
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">SCHOOL MENUS</span>
            <h1>{{ $school->name }}</h1>
            <p>Choose which pages this school may use. Roles and users in this school can never go beyond this list.</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('platform.schools.edit', $school) }}">Back to school</a>
    </div>

    @if (session('status'))
        <div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert">
            <strong>Menus were not saved.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form class="mt-4" method="POST" action="{{ route('platform.schools.access.update', $school) }}">
        @csrf
        @method('PUT')

        <div class="school-access-summary">
            <div>
                <span class="school-access-summary-mark">A</span>
                <span><strong>School feature boundary</strong><small>{{ count($checkedMenuIds) }} of {{ $groups->sum(fn ($group) => $group->children->count()) }} pages selected.</small></span>
            </div>
            <span class="school-access-summary-badge">Super Admin control</span>
        </div>

        <div class="school-access-grid">
            @forelse ($groups as $group)
                <article class="school-access-card" data-check-scope>
                    <div class="school-access-card-heading">
                        <div class="school-module-choice">
                            <span class="school-module-mark">{{ strtoupper(substr($group->name, 0, 1)) }}</span>
                            <span>
                                <strong>{{ $group->name }}</strong>
                                <small>{{ $group->children->count() }} pages</small>
                            </span>
                        </div>
                        @if ($group->children->isNotEmpty())
                            <label class="check-all-toggle"><input class="form-check-input" type="checkbox" data-check-all><span>Select all</span></label>
                        @endif
                    </div>

                    @if ($group->children->isEmpty())
                        <p class="school-no-menus">No active pages in this group yet.</p>
                    @else
                        <div class="school-menu-groups">
                            <div class="school-menu-options">
                                @foreach ($group->children as $menu)
                                    <label class="school-menu-choice" for="menu-{{ $menu->id }}">
                                        <input id="menu-{{ $menu->id }}" class="form-check-input" name="menu_ids[]" type="checkbox" value="{{ $menu->id }}" @checked(in_array((int) $menu->id, $checkedMenuIds, true))>
                                        <span>{{ $menu->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="dashboard-empty-state">
                    <h2>No menus found</h2>
                    <p>Run the navigation seeder to load the menu catalog.</p>
                </div>
            @endforelse
        </div>

        <div class="school-form-actions school-access-actions">
            <a class="btn school-secondary-button" href="{{ route('platform.schools.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">Save menus</button>
        </div>
    </form>
@endsection
