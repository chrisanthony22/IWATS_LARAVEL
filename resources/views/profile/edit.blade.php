<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight tracking-tight t-text">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-10">
        {{-- 1 column on phones and tablets, 2 columns side by side from the lg breakpoint (1024px) up --}}
        <div class="max-w-7xl mx-auto grid grid-cols-1 gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">

            <div class="p-4 sm:p-8 glass rounded-2xl">
                <div class="w-full">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 glass rounded-2xl">
                <div class="w-full">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- Delete account is switched off for now. Remove these comment marks to bring it back (it will span both columns):
            <div class="p-4 sm:p-8 glass rounded-2xl lg:col-span-2">
                <div class="w-full">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
            --}}

        </div>
    </div>
</x-app-layout>