<section>
    <header>
        <h2 class="text-lg font-bold tracking-tight t-text">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm t-muted">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        {{-- Name --}}
        <div>
            <label for="name" class="block text-[13px] font-medium t-muted">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                   required autofocus autocomplete="name"
                   class="t-input mt-1 block h-9 w-full rounded-lg px-3 text-sm">
            <x-input-error class="mt-1.5 text-[13px] !text-red-500" :messages="$errors->get('name')" />
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="block text-[13px] font-medium t-muted">{{ __('Email address') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                   required autocomplete="username"
                   class="t-input mt-1 block h-9 w-full rounded-lg px-3 text-sm">
            <x-input-error class="mt-1.5 text-[13px] !text-red-500" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-sm t-muted">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification"
                                class="rounded text-sm font-medium t-text underline underline-offset-4 hover:opacity-80
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-emerald-500">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-1">
            <button type="submit"
                    class="t-btn h-9 rounded-lg px-5 text-sm font-bold shadow-sm active:scale-[0.99] focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300/30">
                {{ __('Save') }}
            </button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }"
                   x-show="show"
                   x-transition
                   x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm font-medium text-emerald-500">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>