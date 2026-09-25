@extends('layouts.public', [
    'title' => 'Sign in',
    'description' => 'Securely sign in to your school management workspace.',
])

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
@endpush

@section('content')
    <section class="auth-section">
        <div class="auth-orb auth-orb-one" aria-hidden="true"></div>
        <div class="auth-orb auth-orb-two" aria-hidden="true"></div>

        <div class="container app-container position-relative">
            <div class="auth-card mx-auto">
                <div class="auth-card-accent" aria-hidden="true"></div>

                <div class="auth-heading">
                    <span class="auth-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M7.5 10V7.5a4.5 4.5 0 0 1 9 0V10" />
                            <rect x="5" y="10" width="14" height="10" rx="2.5" />
                            <path d="M12 14v2.5" />
                        </svg>
                    </span>
                    <span class="section-kicker">SECURE ACCESS</span>
                    <h1>Welcome back</h1>
                    <p>Sign in with the account provided by your platform or school administrator.</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="email">Email address</label>
                        <input
                            id="email"
                            class="form-control app-form-control @error('email') is-invalid @enderror"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            autofocus
                            required
                            placeholder="you@example.com"
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label app-form-label" for="password">Password</label>
                        <input
                            id="password"
                            class="form-control app-form-control @error('password') is-invalid @enderror"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            placeholder="Enter your password"
                        >
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-4">
                        <input id="remember" class="form-check-input" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                        <label class="form-check-label auth-remember-label" for="remember">Keep me signed in on this device</label>
                    </div>

                    <button class="btn btn-primary app-btn-primary auth-submit" type="submit">
                        Sign in to workspace
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </form>

                <p class="auth-help mb-0">
                    Trouble signing in? Contact your school administrator.
                </p>
            </div>
        </div>
    </section>
@endsection
