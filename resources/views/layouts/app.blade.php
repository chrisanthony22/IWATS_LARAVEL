<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" href="{{ asset('images/logo2.png') }}">

        <title>{{ config('app.name', 'Island Water') }}</title>

        @include('layouts.theme-init')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=nunito:400,500,600,700,800" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" style="font-family: 'Nunito', ui-sans-serif, system-ui, sans-serif;">

        {{-- NOTE: no overflow-hidden here. It would stop the sticky bar from working. --}}
        <div class="theme-page relative min-h-screen">

            {{-- Decorative bubbles (clipped by their own fixed container) --}}
            <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
                <span class="t-bubble absolute -top-16 -left-16 h-64 w-64 rounded-full"></span>
                <span class="t-bubble absolute bottom-16 left-12 h-36 w-36 rounded-full"></span>
                <span class="t-bubble absolute -bottom-24 left-1/2 h-72 w-72 rounded-full"></span>
                <span class="t-bubble absolute top-32 right-16 h-40 w-40 rounded-full blur-2xl"></span>
            </div>

            <div class="relative z-10">

                {{-- Fixed top bar: navigation + page title stay visible while scrolling --}}
                <div class="sticky top-0 z-40">
                    @include('layouts.navigation')

                    @isset($header)
                        <header class="glass rounded-none border-x-0 border-t-0 shadow-none">
                            <div class="max-w-7xl mx-auto py-3 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset
                </div>

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
        <x-page-loader />
    </body>
</html>