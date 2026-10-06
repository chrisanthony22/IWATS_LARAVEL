<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Island Water') }} – Forgot password</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=nunito:400,500,600,700,800" rel="stylesheet" />

    @include('layouts.theme-init')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased" style="font-family: 'Nunito', ui-sans-serif, system-ui, sans-serif;">

    {{-- Full-screen dark blue gradient panel --}}
    <div class="relative min-h-screen overflow-hidden flex items-center justify-center p-6 sm:p-10
                theme-page">

        {{-- Decorative bubbles --}}
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <span class="absolute -top-16 -left-16 h-64 w-64 rounded-full t-bubble"></span>
            <span class="absolute top-1/3 left-1/3 h-20 w-20 rounded-full t-bubble"></span>
            <span class="absolute bottom-16 left-12 h-36 w-36 rounded-full t-bubble"></span>
            <span class="absolute -bottom-24 left-1/2 h-72 w-72 rounded-full t-bubble"></span>
            <span class="absolute top-16 right-16 h-40 w-40 rounded-full t-bubble blur-2xl"></span>
            <span class="absolute bottom-10 right-24 h-32 w-32 rounded-full t-bubble blur-xl"></span>
        </div>

<button type="button" title="Switch theme" aria-label="Switch theme"
                onclick="var t=document.documentElement.getAttribute('data-theme')==='light'?'blue':'light';document.documentElement.setAttribute('data-theme',t);try{localStorage.setItem('theme',t)}catch(e){};window.dispatchEvent(new Event('themechange'))"
                style="width:2.25rem;height:2.25rem;color:var(--text,#fff)"
                class="absolute right-4 top-4 z-20 inline-flex items-center justify-center rounded-lg transition hover:bg-[color:var(--hover)]">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/></svg>
        </button>

        <div class="relative z-10 w-full max-w-4xl grid md:grid-cols-2 items-center gap-10 lg:gap-16">

            {{-- LEFT: hero text --}}
            <div class="hidden md:block">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('images/logo2.png') }}" alt="Island Water" class="logo-blue h-20 w-auto">
                    <img src="{{ asset('images/logo-blue.png') }}" alt="Island Water" class="logo-light h-20 w-auto">
                    <h1 class="text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight">IWATS</h1>
                </div>
                <p class="mt-4 max-w-sm text-base leading-relaxed t-muted">
                    Log in to manage your bookings, transactions and accounts.
                </p>
                <p class="mt-10 text-sm t-muted">By Shogun Ships</p>
            </div>

            {{-- RIGHT: glass card --}}
            <div class="w-full max-w-md justify-self-center md:justify-self-end glass rounded-2xl p-8 sm:p-10">

                {{-- Logo on mobile only --}}
                <img src="{{ asset('images/logo-white.png') }}" alt="Island Water" class="logo-blue h-11 w-auto mb-6 md:hidden">
                <img src="{{ asset('images/logo-blue.png') }}" alt="Island Water" class="logo-light h-11 w-auto mb-6 md:hidden">

                <h2 class="text-2xl font-bold tracking-tight">Forgot password?</h2>
                <p class="mt-1.5 mb-8 text-sm leading-relaxed t-muted">
                    {{ __('No problem. Enter your email address and we will send you a link to choose a new password.') }}
                </p>

                <x-auth-session-status class="mb-4 !text-[color:var(--accent)]" :status="session('status')" />

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                    @csrf

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-[13px] font-medium t-muted">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               required autofocus placeholder="name@company.com"
                               class="t-input mt-1.5 block h-11 w-full rounded-lg px-3.5 text-sm">
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-[13px] !text-red-500" />
                    </div>

                    {{-- Button --}}
                    <button type="submit"
                            class="t-btn h-11 w-full rounded-lg text-sm font-bold shadow-sm active:scale-[0.99] focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300/30">
                        {{ __('Email Password Reset Link') }}
                    </button>

                    {{-- Back to login --}}
                    <p class="text-center">
                        <a href="{{ route('login') }}"
                           class="text-[13px] font-medium t-accent transition hover:underline underline-offset-4
                                  focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200 rounded">
                            Back to log in
                        </a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</body>
</html>