<section>
    <header>
        <h2 class="text-lg font-bold tracking-tight t-text">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm t-muted">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-[13px] font-medium t-muted">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
                   class="t-input mt-1 block h-9 w-full rounded-lg px-3 text-sm">
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1.5 text-[13px] !text-red-500" />
        </div>

        <div>
            <label for="update_password_password" class="block text-[13px] font-medium t-muted">{{ __('New Password') }}</label>
            <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                   class="t-input mt-1 block h-9 w-full rounded-lg px-3 text-sm">
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1.5 text-[13px] !text-red-500" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-[13px] font-medium t-muted">{{ __('Confirm Password') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                   class="t-input mt-1 block h-9 w-full rounded-lg px-3 text-sm">
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1.5 text-[13px] !text-red-500" />
        </div>

        <div class="flex items-center gap-4 pt-1">
            <button type="submit"
                    class="t-btn h-9 rounded-lg px-5 text-sm font-bold shadow-sm active:scale-[0.99] focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300/30">
                {{ __('Save') }}
            </button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }"
                   x-show="show"
                   x-transition
                   x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm font-medium text-emerald-500">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>