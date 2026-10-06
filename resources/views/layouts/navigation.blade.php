<nav x-data="{ open: false }" class="t-nav relative z-40">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-14 justify-between">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <img src="{{ asset('images/iwlogo3.png') }}" alt="{{ config('app.name') }}" class="logo-blue block h-10 w-auto">
                        <img src="{{ asset('images/iwlogo3.png') }}" alt="{{ config('app.name') }}" class="logo-light block h-10 w-auto">
                    </a>
                </div>

                <!-- Navigation Links (desktop) -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @php $salesActive = request()->routeIs('dashboard', 'dashboard.*', 'agent-sales.dashboard'); @endphp
                    <div class="relative flex" x-data="{ sub: false }" @click.outside="sub = false" @keydown.escape.window="sub = false">
                        <button type="button" @click="sub = ! sub" :aria-expanded="sub"
                                class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-semibold transition focus:outline-none
                                       {{ $salesActive ? 'border-[color:var(--accent)] t-text' : 'border-transparent t-muted hover:t-text' }}">
                            {{ __('SalesGraph') }}
                            <svg class="ms-1 h-4 w-4 fill-current transition" :class="{ 'rotate-180': sub }" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-show="sub" x-cloak x-transition.opacity
                             class="absolute left-0 top-full z-50 w-56 rounded-lg py-1 shadow-lg ring-1 ring-black/10
                                    t-dropdown">
                            <a href="{{ route('dashboard') }}"
                               class="block px-4 py-2 text-sm font-semibold transition hover:bg-[color:var(--hover)]
                                      {{ request()->routeIs('dashboard', 'dashboard.*') ? 't-text' : 't-muted' }}">
                                {{ __('Dashboard') }}
                            </a>
                            <a href="{{ route('agent-sales.dashboard') }}"
                               class="block px-4 py-2 text-sm font-semibold transition hover:bg-[color:var(--hover)]
                                      {{ request()->routeIs('agent-sales.dashboard') ? 't-text' : 't-muted' }}">
                                {{ __('Agent Sales Dashboard') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-1">
                <button type="button" title="Switch theme" aria-label="Switch theme"
                        onclick="var t=document.documentElement.getAttribute('data-theme')==='light'?'blue':'light';document.documentElement.setAttribute('data-theme',t);try{localStorage.setItem('theme',t)}catch(e){};window.dispatchEvent(new Event('themechange'))"
                        style="width:2.25rem;height:2.25rem;color:var(--text,#fff)"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg transition hover:bg-[color:var(--hover)] ">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>
                    </svg>
                </button>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-semibold t-text
                                       transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center gap-1 sm:hidden">
                <button type="button" title="Switch theme" aria-label="Switch theme"
                        onclick="var t=document.documentElement.getAttribute('data-theme')==='light'?'blue':'light';document.documentElement.setAttribute('data-theme',t);try{localStorage.setItem('theme',t)}catch(e){};window.dispatchEvent(new Event('themechange'))"
                        style="width:2.25rem;height:2.25rem;color:var(--text,#fff)"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg transition hover:bg-[color:var(--hover)] ">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>
                    </svg>
                </button>
                <button @click="open = ! open"
                        class="inline-flex items-center justify-center rounded-lg p-2 t-text
                               transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200"
                        aria-label="Toggle menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (mobile) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t t-divider sm:hidden">
        <div class="space-y-1 px-2 pt-2 pb-3" x-data="{ sub: {{ request()->routeIs('dashboard', 'dashboard.*', 'agent-sales.dashboard') ? 'true' : 'false' }} }">
            <button type="button" @click="sub = ! sub"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-base font-semibold t-muted transition hover:bg-[color:var(--hover)]">
                {{ __('SalesGraph') }}
                <svg class="h-4 w-4 fill-current transition" :class="{ 'rotate-180': sub }" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>

            <div x-show="sub" x-cloak class="space-y-1 ps-4">
                <a href="{{ route('dashboard') }}"
                   class="block rounded-lg px-3 py-2 text-base font-semibold transition
                          {{ request()->routeIs('dashboard', 'dashboard.*') ? 'bg-[color:var(--hover)] t-text' : 't-muted hover:bg-[color:var(--hover)]' }}">
                    {{ __('Dashboard') }}
                </a>
                <a href="{{ route('agent-sales.dashboard') }}"
                   class="block rounded-lg px-3 py-2 text-base font-semibold transition
                          {{ request()->routeIs('agent-sales.dashboard') ? 'bg-[color:var(--hover)] t-text' : 't-muted hover:bg-[color:var(--hover)]' }}">
                    {{ __('Agent Sales Dashboard') }}
                </a>
            </div>
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t t-divider pt-4 pb-3">
            <div class="px-4">
                <div class="text-base font-semibold t-text">{{ Auth::user()->name }}</div>
                <div class="text-sm t-muted">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1 px-2">
                <a href="{{ route('profile.edit') }}"
                   class="block rounded-lg px-3 py-2 text-base font-semibold transition
                          {{ request()->routeIs('profile.edit')
                                ? 'bg-[color:var(--hover)] t-text'
                                : 't-muted hover:bg-[color:var(--hover)]' }}">
                    {{ __('Profile') }}
                </a>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <a href="{{ route('logout') }}"
                       onclick="event.preventDefault(); this.closest('form').submit();"
                       class="block rounded-lg px-3 py-2 text-base font-semibold t-muted transition hover:bg-[color:var(--hover)]">
                        {{ __('Log Out') }}
                    </a>
                </form>
            </div>
        </div>
    </div>
</nav>