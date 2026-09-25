@extends('layouts.dashboard', ['title' => 'Roles', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">ACCESS CONTROL</span>
            <h1>Roles</h1>
            <p><strong>Platform roles</strong> (such as Principal) are created and assigned only by Super Admin and work in every school. <strong>School roles</strong> belong to one school.</p>
        </div>
        <div class="school-heading-actions">
            <a class="btn school-secondary-button" href="{{ route('platform.roles.create', ['type' => 'school', 'school_id' => $selectedSchoolId]) }}">+ School role</a>
            <a class="btn btn-primary app-btn-primary" href="{{ route('platform.roles.create', ['type' => 'platform']) }}">+ Platform role</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>
    @endif

    <section class="dashboard-panel mt-4">
        <form class="school-filter-bar" method="GET" action="{{ route('platform.roles.index') }}">
            <div class="school-search-field">
                <label class="visually-hidden" for="search">Search roles</label>
                <input id="search" class="form-control app-form-control" name="search" value="{{ $search }}" maxlength="100" placeholder="Search by role name">
            </div>
            <div>
                <label class="visually-hidden" for="type">Role type</label>
                <select id="type" class="form-select app-form-control" name="type">
                    <option value="">All role types</option>
                    <option value="platform" @selected($selectedType?->value === 'platform')>Platform roles</option>
                    <option value="school" @selected($selectedType?->value === 'school')>School roles</option>
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="school_id">School</label>
                <select id="school_id" class="form-select app-form-control" name="school_id">
                    <option value="">All schools</option>
                    @foreach ($schools as $schoolOption)
                        <option value="{{ $schoolOption->id }}" @selected($selectedSchoolId === $schoolOption->id)>{{ $schoolOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn school-filter-button" type="submit">Filter</button>
            @if ($search !== '' || $selectedType !== null || $selectedSchoolId !== null)
                <a class="btn school-clear-button" href="{{ route('platform.roles.index') }}">Clear</a>
            @endif
        </form>

        @if ($roles->isEmpty())
            <div class="school-list-empty">
                <span>R</span>
                <h2>No roles found</h2>
                <p>Start with a platform role such as “Principal”, then add school roles like Teacher or Accountant.</p>
            </div>
        @else
            <div class="role-card-grid">
                @foreach ($roles as $roleItem)
                    <article class="role-card">
                        <div class="role-card-top">
                            <span class="role-card-mark">{{ strtoupper(substr($roleItem->name, 0, 1)) }}</span>
                            <span class="school-status {{ $roleItem->is_active ? 'school-status-active' : 'school-status-cancelled' }}">{{ $roleItem->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <div class="role-card-copy">
                            <div class="role-card-title">
                                <h2>{{ $roleItem->name }}</h2>
                                <span>{{ $roleItem->isPlatform() ? 'Platform' : 'School' }}</span>
                            </div>
                            <code>{{ $roleItem->isPlatform() ? 'All schools' : $roleItem->school?->name }}</code>
                            <p>{{ $roleItem->description ?: 'No description has been added.' }}</p>
                        </div>
                        <div class="role-card-footer">
                            <span>
                                @if ($roleItem->all_school_menus)
                                    <strong>All</strong> school menus
                                @else
                                    <strong>{{ number_format($roleItem->menus_count) }}</strong> menus
                                @endif
                                &middot; <strong>{{ number_format($roleItem->users_count) }}</strong> users
                            </span>
                            <a class="btn school-row-action" href="{{ route('platform.roles.edit', $roleItem) }}">Configure</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="school-pagination">{{ $roles->links() }}</div>
        @endif
    </section>
@endsection
