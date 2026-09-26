<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} · {{ config('app.name', 'School Management') }}</title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/dashboard.css') }}?v=20260926-8" rel="stylesheet">
</head>
<body class="dashboard-body">
    <a class="skip-link" href="#dashboard-content">Skip to content</a>

    <div class="dashboard-shell">
        <aside class="dashboard-sidebar">
            <a class="app-brand dashboard-brand" href="{{ route('dashboard') }}">
                <span class="app-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 9.75 12 5l9 4.75L12 14.5 3 9.75Z" />
                        <path d="M6.5 12v4.25c2.9 2.35 8.1 2.35 11 0V12" />
                    </svg>
                </span>
                <span>{{ config('app.name', 'School Management') }}</span>
            </a>

            <div class="dashboard-workspace">
                <span>{{ $panelLabel ?? 'Workspace' }}</span>
                <strong>{{ $workspaceName ?? 'Management Panel' }}</strong>
            </div>

            <nav class="dashboard-nav" aria-label="Dashboard navigation">
                <span class="dashboard-nav-label">Overview</span>
                <a class="dashboard-nav-link {{ request()->routeIs('dashboard', 'platform.dashboard', 'school.dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <span class="dashboard-nav-icon" aria-hidden="true">
                        <x-sidebar-icon name="dashboard" />
                    </span>
                    Dashboard
                </a>

                @if (auth()->user()?->isSuperAdmin())
                    <span class="dashboard-nav-label mt-4">Platform</span>
                    <a class="dashboard-nav-link {{ request()->routeIs('platform.schools.*') ? 'active' : '' }}" href="{{ route('platform.schools.index') }}">
                        <span class="dashboard-nav-icon" aria-hidden="true">
                            <x-sidebar-icon name="schools" />
                        </span>
                        Schools
                    </a>
                    <a class="dashboard-nav-link {{ request()->routeIs('platform.roles.*') ? 'active' : '' }}" href="{{ route('platform.roles.index') }}">
                        <span class="dashboard-nav-icon" aria-hidden="true"><x-sidebar-icon name="roles" /></span>
                        Roles
                    </a>
                    <a class="dashboard-nav-link {{ request()->routeIs('platform.users.*') ? 'active' : '' }}" href="{{ route('platform.users.index') }}">
                        <span class="dashboard-nav-icon" aria-hidden="true"><x-sidebar-icon name="users" /></span>
                        Users
                    </a>
                    <a class="dashboard-nav-link {{ request()->routeIs('platform.audit-logs.*') ? 'active' : '' }}" href="{{ route('platform.audit-logs.index') }}">
                        <span class="dashboard-nav-icon" aria-hidden="true"><x-sidebar-icon name="audit_logs" /></span>
                        Audit logs
                    </a>
                @endif

                @if (($databaseNavigation ?? collect())->isNotEmpty())
                    {{-- Collapsible groups: the group of the open page starts expanded, the rest collapsed
                         unless the user opened them before (remembered by dashboard.js). --}}
                    @foreach ($databaseNavigation as $groupName => $groupMenus)
                        @php
                            $groupId = 'nav-group-'.\Illuminate\Support\Str::slug($groupName);
                            $activeMenuKeys = $groupMenus
                                ->filter(fn ($menuItem) => request()->routeIs(str_ends_with($menuItem->route_name, '.index') ? substr($menuItem->route_name, 0, -5).'*' : $menuItem->route_name))
                                ->pluck('key');
                            $groupIsActive = $activeMenuKeys->isNotEmpty();
                        @endphp
                        <div class="dashboard-nav-group {{ $groupIsActive ? '' : 'is-collapsed' }}" data-nav-group="{{ $groupId }}" @if ($groupIsActive) data-nav-group-active @endif>
                            <button class="dashboard-nav-label dashboard-nav-group-toggle mt-4" type="button" aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}" aria-controls="{{ $groupId }}">
                                <span>{{ $groupName }}</span>
                                <svg class="dashboard-nav-group-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </button>
                            <div class="dashboard-nav-group-items" id="{{ $groupId }}">
                                @foreach ($groupMenus as $menuItem)
                                    <a class="dashboard-nav-link {{ $activeMenuKeys->contains($menuItem->key) ? 'active' : '' }}" href="{{ route($menuItem->route_name) }}">
                                        <span class="dashboard-nav-icon" aria-hidden="true"><x-sidebar-icon :name="$menuItem->key" /></span>
                                        {{ $menuItem->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @elseif (! auth()->user()?->isSuperAdmin())
                    <span class="dashboard-nav-label mt-4">Modules</span>
                    <div class="dashboard-nav-coming">
                        <span class="dashboard-nav-dot"></span>
                        No modules assigned yet
                    </div>
                @endif
            </nav>

            <div class="dashboard-sidebar-footer">
                <div class="dashboard-user">
                    <span class="dashboard-user-avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 2)) }}</span>
                    <span>
                        <strong>{{ auth()->user()?->name ?? 'User' }}</strong>
                        <small>{{ auth()->user()?->email }}</small>
                    </span>
                </div>

            </div>
        </aside>

        <div class="dashboard-main">
            <header class="dashboard-topbar">
                <div>
                    <span class="dashboard-topbar-label">{{ $panelLabel ?? 'Workspace' }}</span>
                    <strong>{{ $title ?? 'Dashboard' }}</strong>
                </div>
                <div class="dashboard-topbar-actions">
                    @if (auth()->user()?->isSuperAdmin())
                        <div class="dropdown" data-school-switcher data-options-url="{{ route('platform.schools.options') }}" data-active-school="{{ $activeSchool?->getKey() }}">
                            <button
                                class="dashboard-school-button"
                                type="button"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false"
                                aria-label="Switch school"
                            >
                                <x-sidebar-icon name="schools" />
                                <span>{{ $activeSchool?->name ?? 'Select school' }}</span>
                                <svg class="dashboard-school-caret" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end dashboard-account-menu dashboard-school-menu">
                                <div class="dashboard-account-menu-header">
                                    <strong>Switch school</strong>
                                    <small>{{ $activeSchool ? 'Working in '.$activeSchool->name : 'No school selected' }}</small>
                                </div>
                                <input
                                    class="form-control form-control-sm dashboard-school-search"
                                    type="search"
                                    maxlength="100"
                                    placeholder="Search by name or code"
                                    aria-label="Search schools"
                                    data-school-search
                                >
                                <div class="dashboard-school-list" data-school-results></div>
                                <button class="dropdown-item dashboard-school-more" type="button" data-school-more hidden>Load more</button>
                                @if ($activeSchool)
                                    <div class="dropdown-divider"></div>
                                    <form method="POST" action="{{ route('platform.schools.leave') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="dropdown-item" type="submit">
                                            <span aria-hidden="true">←</span> Back to Platform
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="dropdown">
                        <button
                            class="dashboard-settings-button"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            aria-label="Open account menu"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
                                <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 8.94 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.57 15 1.7 1.7 0 0 0 3 14H3v-4h.08A1.7 1.7 0 0 0 4.6 8.94a1.7 1.7 0 0 0-.34-1.88L4.2 7l2.83-2.83.06.06a1.7 1.7 0 0 0 1.88.34H9A1.7 1.7 0 0 0 10 3.08V3h4v.08A1.7 1.7 0 0 0 15.06 4.6a1.7 1.7 0 0 0 1.88-.34L17 4.2 19.83 7l-.06.06a1.7 1.7 0 0 0-.34 1.88V9A1.7 1.7 0 0 0 20.92 10H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z" />
                            </svg>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end dashboard-account-menu">
                            <div class="dashboard-account-menu-header">
                                <strong>{{ auth()->user()?->name ?? 'User' }}</strong>
                                <small>{{ auth()->user()?->email }}</small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('account.edit') }}">
                                <span aria-hidden="true">⚙</span> Account settings
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item dashboard-signout-item" type="submit">
                                    <span aria-hidden="true">↗</span> Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main id="dashboard-content" class="dashboard-content">
                @yield('content')
            </main>

            <footer class="dashboard-footer">
                &copy; {{ now()->year }} Ranjan Chauhan. All rights reserved.
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/dashboard.js') }}?v=20260926-5" defer></script>
</body>
</html>
