<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <meta name="description" content="{{ $description ?? 'A modern school management platform.' }}">

    <title>{{ isset($title) ? $title.' · '.config('app.name', 'School Management') : config('app.name', 'School Management') }}</title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>

    <nav class="navbar navbar-expand-lg app-navbar" aria-label="Primary navigation">
        <div class="container app-container">
            <a class="navbar-brand app-brand" href="{{ route('home') }}">
                <span class="app-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="img">
                        <path d="M3 9.75 12 5l9 4.75L12 14.5 3 9.75Z" />
                        <path d="M6.5 12v4.25c2.9 2.35 8.1 2.35 11 0V12" />
                    </svg>
                </span>
                <span>{{ config('app.name', 'School Management') }}</span>
            </a>

            <div class="d-flex align-items-center gap-2">
                <span class="navbar-text d-none d-md-inline-flex app-navbar-badge">
                    Built for modern schools
                </span>
                @auth
                    <a class="btn app-navbar-login" href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a class="btn app-navbar-login" href="{{ route('login') }}">Sign in</a>
                @endauth
            </div>
        </div>
    </nav>

    <main id="main-content">
        @yield('content')
    </main>

    <footer class="app-footer">
        <div class="container app-container d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
            <p class="mb-0">&copy; {{ now()->year }} Ranjan Chauhan. All rights reserved.</p>
            <p class="mb-0">Secure multi-school foundation</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
