@php
    $glass  = 'glass rounded-2xl';
    $select = 't-input mt-1 block h-9 w-full rounded-lg px-3 text-sm [&>option]:text-slate-900';
    $input  = 't-input mt-1 block h-9 w-full rounded-lg px-3 text-sm';
    $iconBtn = 't-muted rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight tracking-tight t-text">
            {{ __('User Accounts') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto grid gap-4 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">

            {{-- Flash messages --}}
            @if (session('success'))
                <div class="{{ $glass }} border border-emerald-500/40 px-5 py-3 text-sm font-semibold lg:col-span-12" role="status">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="{{ $glass }} border border-red-500/50 px-5 py-3 text-sm font-semibold lg:col-span-12" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Filters + add --}}
            <div class="{{ $glass }} p-5 lg:col-span-12">
                <form method="GET" onsubmit="window.Loader && Loader.show('Loading users…')"
                      class="flex flex-wrap items-end gap-4">
                    <label class="block t-muted text-[13px] font-medium w-full sm:w-64">
                        Search
                        <input type="search" name="q" value="{{ $q }}" placeholder="Name or email" class="{{ $input }}">
                    </label>

                    <label class="block t-muted text-[13px] font-medium w-full sm:w-52">
                        User Type
                        <select name="type" class="{{ $select }}">
                            <option value="0">All</option>
                            @foreach ($userTypes as $t)
                                <option value="{{ $t->ID }}" @selected($type === (int) $t->ID)>{{ $t->UserType }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit"
                            class="h-9 rounded-lg bg-sky-600 px-5 text-sm font-semibold text-white transition hover:bg-sky-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        Load
                    </button>

                    <button type="button" onclick="openUserForm('create')"
                            class="t-text ms-auto inline-flex h-9 items-center gap-1.5 rounded-lg border border-sky-500/60 px-4 text-sm font-semibold transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Add User
                    </button>
                </form>
            </div>

            {{-- Users table --}}
            <div class="{{ $glass }} overflow-hidden lg:col-span-12">
                <div class="overflow-auto">
                    <table class="w-full min-w-[48rem] text-left text-sm">
                        <thead class="bg-blue-950 text-white">
                            <tr>
                                <th class="w-12 px-4 py-3 text-center font-bold">#</th>
                                <th class="px-4 py-3 font-bold">Name</th>
                                <th class="px-4 py-3 font-bold">Email</th>
                                <th class="px-4 py-3 text-center font-bold">User Type</th>
                                <th class="px-4 py-3 text-center font-bold">Created</th>
                                <th class="px-4 py-3 text-center font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $u)
                                <tr class="border-b t-divider transition hover:bg-[color:var(--hover)]">
                                    <td class="t-muted px-4 py-2.5 text-center">{{ $users->firstItem() + $loop->index }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 font-semibold">
                                        {{ $u->name }}
                                        @if ($u->id === auth()->id()) <span class="t-muted text-xs font-normal">(you)</span> @endif
                                    </td>
                                    <td class="px-4 py-2.5">{{ $u->email }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if ($u->usertype_name)
                                            <span class="inline-block rounded-full border t-ring px-2.5 py-0.5 text-xs font-semibold">{{ $u->usertype_name }}</span>
                                        @else
                                            <span class="t-muted text-xs">Not assigned</span>
                                        @endif
                                    </td>
                                    <td class="t-muted whitespace-nowrap px-4 py-2.5 text-center">{{ $u->created_at?->format('M d, Y') }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-center gap-1">
                                            {{-- Edit --}}
                                            <button type="button" title="Edit" aria-label="Edit {{ $u->name }}" class="{{ $iconBtn }}"
                                                    data-user="{{ json_encode(['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'usertype_id' => $u->usertype_id]) }}"
                                                    onclick="openUserForm('edit', JSON.parse(this.dataset.user))">
                                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                                            </button>
                                            {{-- Reset password --}}
                                            <button type="button" title="Send new password" aria-label="Send new password to {{ $u->name }}" class="{{ $iconBtn }}"
                                                    onclick="openConfirm({
                                                        title: 'Send a new password?',
                                                        text: @js('A new random password will be emailed to ' . $u->email . '. The current password stops working.'),
                                                        action: @js(route('users.reset-password', $u->id)),
                                                        method: 'POST', button: 'Send new password' })">
                                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3M16 7l3 3M14 9l2 2"/></svg>
                                            </button>
                                            {{-- Delete --}}
                                            @if ($u->id !== auth()->id())
                                                <button type="button" title="Delete" aria-label="Delete {{ $u->name }}" class="{{ $iconBtn }} hover:!text-red-400"
                                                        onclick="openConfirm({
                                                            title: 'Delete this user?',
                                                            text: @js($u->name . ' (' . $u->email . ') will lose access. This cannot be undone.'),
                                                            action: @js(route('users.destroy', $u->id)),
                                                            method: 'DELETE', button: 'Delete user', danger: true })">
                                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="t-muted p-6 text-center text-sm">
                                        No users match these filters. Clear the search or add a new user.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="border-t t-divider px-5 py-3">{{ $users->links() }}</div>
                @endif
            </div>

        </div>
    </div>

    {{-- Add / edit modal --}}
    <div id="userModal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeUserForm()"></div>

        <div class="glass relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl">
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <h3 id="userModalTitle" class="text-base font-bold">Add User</h3>
                <button type="button" onclick="closeUserForm()" aria-label="Close"
                        class="t-text rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <form id="userForm" method="POST" action="{{ route('users.store') }}"
                  onsubmit="window.Loader && Loader.show('Saving…')" class="space-y-4 overflow-auto px-5 pb-5">
                @csrf
                <input type="hidden" name="_method" id="userMethod" value="PUT" disabled>
                <input type="hidden" name="_mode" id="userMode" value="create">
                <input type="hidden" name="_id" id="userId" value="">

                <label class="block t-muted text-[13px] font-medium">
                    Full Name
                    <input type="text" name="name" id="userName" value="{{ old('name') }}" required maxlength="255" autocomplete="off" class="{{ $input }}">
                    @error('name') <span class="mt-1 block text-xs font-semibold text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="block t-muted text-[13px] font-medium">
                    Email
                    <input type="email" name="email" id="userEmail" value="{{ old('email') }}" required maxlength="255" autocomplete="off" class="{{ $input }}">
                    @error('email') <span class="mt-1 block text-xs font-semibold text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="block t-muted text-[13px] font-medium">
                    User Type
                    <select name="usertype_id" id="userType" required class="{{ $select }}">
                        <option value="">Select a user type</option>
                        @foreach ($userTypes as $t)
                            <option value="{{ $t->ID }}" @selected((string) old('usertype_id') === (string) $t->ID)>{{ $t->UserType }}</option>
                        @endforeach
                    </select>
                    @error('usertype_id') <span class="mt-1 block text-xs font-semibold text-red-400">{{ $message }}</span> @enderror
                </label>

                <p id="userPwNote" class="t-muted rounded-lg border t-ring bg-white/5 px-3 py-2 text-xs">
                    A random password is generated and emailed to this address. You will not see it.
                </p>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" onclick="closeUserForm()"
                            class="t-text h-9 rounded-lg border t-ring px-4 text-sm font-semibold transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        Cancel
                    </button>
                    <button type="submit" id="userSubmit"
                            class="h-9 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        Create and email password
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Confirm modal (delete / send new password) --}}
    <div id="confirmModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeConfirm()"></div>

        <div class="glass relative w-full max-w-md overflow-hidden rounded-2xl p-5">
            <h3 id="confirmTitle" class="text-base font-bold"></h3>
            <p id="confirmText" class="t-muted mt-2 text-sm"></p>

            <form id="confirmForm" method="POST" onsubmit="window.Loader && Loader.show('Working…')" class="mt-5 flex justify-end gap-2">
                @csrf
                <input type="hidden" name="_method" id="confirmMethod" value="POST">
                <button type="button" onclick="closeConfirm()"
                        class="t-text h-9 rounded-lg border t-ring px-4 text-sm font-semibold transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                    Cancel
                </button>
                <button type="submit" id="confirmSubmit"
                        class="h-9 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                    Confirm
                </button>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const $ = id => document.getElementById(id);
        const storeUrl  = @json(route('users.store'));
        const updateUrl = @json(route('users.update', '__ID__'));

        const userModal = $('userModal'), confirmModal = $('confirmModal');
        const show = m => { m.classList.remove('hidden'); m.classList.add('flex'); document.body.style.overflow = 'hidden'; };
        const hide = m => { m.classList.add('hidden'); m.classList.remove('flex'); document.body.style.overflow = ''; };

        window.openUserForm = function (mode, u) {
            u = u || {};
            const edit = mode === 'edit';
            $('userForm').action = edit ? updateUrl.replace('__ID__', u.id) : storeUrl;
            $('userMethod').disabled = !edit;                 // PUT only when editing
            $('userMode').value  = mode;
            $('userId').value    = edit ? (u.id ?? '') : '';
            $('userName').value  = u.name ?? '';
            $('userEmail').value = u.email ?? '';
            $('userType').value  = u.usertype_id ?? '';
            $('userPwNote').classList.toggle('hidden', edit);
            $('userModalTitle').textContent = edit ? 'Edit User' : 'Add User';
            $('userSubmit').textContent     = edit ? 'Save changes' : 'Create and email password';
            show(userModal);
            $('userName').focus();
        };
        window.closeUserForm = () => hide(userModal);

        window.openConfirm = function (o) {
            $('confirmTitle').textContent = o.title;
            $('confirmText').textContent  = o.text;
            $('confirmForm').action = o.action;
            $('confirmMethod').value = o.method || 'POST';
            const b = $('confirmSubmit');
            b.textContent = o.button || 'Confirm';
            b.className = 'h-9 rounded-lg px-4 text-sm font-semibold text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 '
                        + (o.danger ? 'bg-red-600 hover:bg-red-500' : 'bg-sky-600 hover:bg-sky-500');
            show(confirmModal);
        };
        window.closeConfirm = () => hide(confirmModal);

        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            closeConfirm(); closeUserForm();
        });

        // validation or mail failed: reopen the form with what the admin typed
        @if (old('_mode'))
            openUserForm(@json(old('_mode')), {
                id: @json(old('_id')),
                name: @json(old('name')),
                email: @json(old('email')),
                usertype_id: @json(old('usertype_id')),
            });
        @endif
    })();
    </script>
</x-app-layout>