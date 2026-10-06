<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/iwlogo3.png') }}">

    <title>{{ config('app.name', 'Island Water') }} – Log in</title>

    @include('layouts.theme-init')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=nunito:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" style="font-family: 'Nunito', ui-sans-serif, system-ui, sans-serif;">

    <div class="theme-page relative min-h-screen overflow-hidden flex items-center justify-center p-6 sm:p-10">

        {{-- Theme toggle --}}
        <x-theme-toggle class="absolute right-4 top-4 z-20" />

        {{-- Decorative bubbles --}}
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <span class="t-bubble absolute -top-16 -left-16 h-64 w-64 rounded-full"></span>
            <span class="t-bubble absolute top-1/3 left-1/3 h-20 w-20 rounded-full"></span>
            <span class="t-bubble absolute bottom-16 left-12 h-36 w-36 rounded-full"></span>
            <span class="t-bubble absolute -bottom-24 left-1/2 h-72 w-72 rounded-full"></span>
            <span class="t-bubble absolute top-16 right-16 h-40 w-40 rounded-full blur-2xl"></span>
            <span class="t-bubble absolute bottom-10 right-24 h-32 w-32 rounded-full blur-xl"></span>
        </div>

        <div class="relative z-10 w-full max-w-4xl grid md:grid-cols-2 items-center gap-10 lg:gap-16">

            {{-- LEFT: hero text --}}
            <div class="hidden md:block">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('images/logo2.png') }}" alt="Island Water" class="logo-blue h-36 w-auto">
                    <img src="{{ asset('images/logo-blue.png') }}" alt="Island Water" class="logo-light h-36 w-auto">
                    <div>
                        <h1 class="text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight">IWATS</h1>
                        <hr class="t-divider">
                        <p class="t-muted mt-2 mb-2 max-w-sm text-base leading-relaxed">
                            Log in to manage your bookings, transactions and accounts.
                        </p>
                        <hr class="t-divider">
                        <p class="mt-1 text-lg">By Shogun Ships</p>
                    </div>
                </div>
            </div>

            {{-- RIGHT: glass login card --}}
            <div class="glass w-full max-w-md justify-self-center md:justify-self-end rounded-2xl p-8 sm:p-10">

                {{-- Logo on mobile only --}}
                <img src="{{ asset('images/logo2.png') }}" alt="Island Water" class="logo-blue mb-6 h-16 w-auto md:hidden">
                <img src="{{ asset('images/logo-blue.png') }}" alt="Island Water" class="logo-light mb-6 h-16 w-auto md:hidden">

                <h2 class="text-2xl font-bold tracking-tight">Sign in</h2>
                <p class="t-muted mt-1.5 mb-8 text-sm">Use your account credentials to continue.</p>

                <x-auth-session-status class="mb-4 !text-[color:var(--accent)]" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="t-muted block text-[13px] font-medium">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               required autofocus autocomplete="username" placeholder="name@company.com"
                               class="t-input mt-1.5 block h-11 w-full rounded-lg px-3.5 text-sm">
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-[13px] !text-red-500" />
                    </div>

                    <div>
                        <label for="password" class="t-muted block text-[13px] font-medium">Password</label>
                        <input id="password" type="password" name="password"
                               required autocomplete="current-password" placeholder="••••••••"
                               class="t-input mt-1.5 block h-11 w-full rounded-lg px-3.5 text-sm">
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-[13px] !text-red-500" />
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label for="remember_me" class="inline-flex items-center gap-2">
                            <input id="remember_me" type="checkbox" name="remember"
                                   class="h-4 w-4 rounded border-[color:var(--input-border)] bg-[color:var(--input-bg)] text-blue-700 focus:ring-sky-300/40">
                            <span class="t-muted text-[13px]">Remember me</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                               class="t-accent rounded text-[13px] font-medium underline-offset-4 transition hover:underline
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    <button type="submit"
                            class="t-btn h-11 w-full rounded-lg text-sm font-bold shadow-sm active:scale-[0.99]
                                   focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300/30">
                        Log in
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>