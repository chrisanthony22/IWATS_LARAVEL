
<!--
<section class="space-y-6">
    <header>
        <h2 class="text-lg font-bold tracking-tight t-text">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm t-muted">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <button type="button"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            class="h-11 rounded-lg bg-red-500 px-6 text-sm font-bold text-white shadow-sm transition
                   hover:bg-red-400 active:scale-[0.99]
                   focus:outline-none focus-visible:ring-4 focus-visible:ring-red-300/40">
        {{ __('Delete Account') }}
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        {{-- Gradient fills the modal panel so it matches the rest of the system --}}
        <form method="post" action="{{ route('profile.destroy') }}"
              class="theme-page p-6 sm:p-8">
            @csrf
            @method('delete')

            <h2 class="text-lg font-bold tracking-tight">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1.5 text-sm leading-relaxed t-muted">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <label for="password" class="sr-only">{{ __('Password') }}</label>

                <input id="password" name="password" type="password"
                       placeholder="{{ __('Password') }}"
                       class="t-input block h-11 w-full rounded-lg px-3.5 text-sm sm:w-3/4">

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1.5 text-[13px] !text-red-500" />
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')"
                        class="h-11 rounded-lg border t-divider bg-[color:var(--hover)] t-text px-5 text-sm font-semibold
                               transition hover:opacity-80
                               focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-200/30">
                    {{ __('Cancel') }}
                </button>

                <button type="submit"
                        class="h-11 rounded-lg bg-red-500 px-5 text-sm font-bold text-white shadow-sm transition
                               hover:bg-red-400 active:scale-[0.99]
                               focus:outline-none focus-visible:ring-4 focus-visible:ring-red-300/40">
                    {{ __('Delete Account') }}
                </button>
            </div>
        </form>
    </x-modal>
</section>
