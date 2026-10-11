@php
    // Shared glass style matching your dashboard design
    $glass = 'glass rounded-2xl';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight tracking-tight t-text">
            {{ __('Access Restricted') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="{{ $glass }} p-8 text-center flex flex-col items-center justify-center min-h-[22rem]">
                
                {{-- 403 Badge --}}
                <div class="mb-4 inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-500/10 border border-red-500/20 text-red-500">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>

                {{-- Title & Subtitle --}}
                <h3 class="text-2xl font-extrabold tracking-tight t-text mb-2">
                    403 - Access Denied
                </h3>
                
                <p class="t-muted text-sm max-w-md mb-6 leading-relaxed">
                    {{ $exception->getMessage() ?: 'You do not have access to this module. Please contact your system administrator if you require access.' }}
                </p>

            </div>
        </div>
    </div>
</x-app-layout>