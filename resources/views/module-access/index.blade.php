@php
    $glass  = 'glass rounded-2xl';
    $select = 't-input mt-1 block h-9 w-full rounded-lg px-3 text-sm [&>option]:text-slate-900';
    $actions = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];
    $box = 'h-4 w-4 cursor-pointer rounded accent-sky-500 disabled:cursor-not-allowed disabled:opacity-60';
    $typeName = $userTypes->firstWhere('ID', $typeId)?->UserType;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight tracking-tight t-text">
            {{ __('Module Access') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto grid gap-4 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">

            @if (session('success'))
                <div class="{{ $glass }} border border-emerald-500/40 px-5 py-3 text-sm font-semibold lg:col-span-12" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="{{ $glass }} border border-red-500/50 px-5 py-3 text-sm font-semibold lg:col-span-12" role="alert">{{ session('error') }}</div>
            @endif

            {{-- Pick the user type --}}
            <form method="GET" onsubmit="window.Loader && Loader.show('Loading access…')"
                  class="{{ $glass }} flex flex-wrap items-end gap-4 p-5 lg:col-span-12">
                <label class="block t-muted text-[13px] font-medium w-full sm:w-64">
                    User Type
                    <select name="type" class="{{ $select }}">
                        @foreach ($userTypes as $t)
                            <option value="{{ $t->ID }}" @selected($typeId === (int) $t->ID)>{{ $t->UserType }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit"
                        class="h-9 rounded-lg bg-sky-600 px-5 text-sm font-semibold text-white transition hover:bg-sky-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                    Load
                </button>
                <p class="t-muted text-xs sm:ms-auto sm:max-w-md">
                    Create, Edit and Delete need View, so ticking them also ticks View. Changes apply as soon as you save.
                </p>
            </form>

            {{-- Access grid --}}
            <form method="POST" action="{{ route('module-access.update') }}"
                  onsubmit="window.Loader && Loader.show('Saving…')" class="{{ $glass }} overflow-hidden lg:col-span-12">
                @csrf
                @method('PUT')
                <input type="hidden" name="usertype_id" value="{{ $typeId }}">

                @if ($locked)
                    <div class="border-b t-divider px-5 py-3 text-sm font-semibold">
                        {{ $typeName }} is an administrator type. It always has full access and cannot be changed here.
                    </div>
                @endif

                <div class="overflow-auto">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead class="bg-blue-950 text-white">
                            <tr>
                                <th class="px-4 py-3 font-bold">Module</th>
                                @foreach ($actions as $label)
                                    <th class="w-24 px-4 py-3 text-center font-bold">{{ $label }}</th>
                                @endforeach
                                <th class="w-24 px-4 py-3 text-center font-bold">All</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($modules as $m)
                                @php $row = $access[(int) $m->ID] ?? []; @endphp
                                <tr class="border-b t-divider transition hover:bg-[color:var(--hover)]" data-row>
                                    <td class="px-4 py-2.5">
                                        <p class="font-semibold">{{ $m->ModuleName }}</p>
                                        <p class="t-muted text-xs">{{ $m->ModuleKey }}</p>
                                    </td>
                                    @foreach ($actions as $key => $label)
                                        <td class="px-4 py-2.5 text-center">
                                            <input type="checkbox" name="access[{{ $m->ID }}][{{ $key }}]" value="1"
                                                   data-action="{{ $key }}" aria-label="{{ $label }} {{ $m->ModuleName }}"
                                                   class="{{ $box }}" @checked($locked || ($row[$key] ?? false)) @disabled($locked)>
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-2.5 text-center">
                                        <input type="checkbox" data-all aria-label="All for {{ $m->ModuleName }}"
                                               class="{{ $box }}" @disabled($locked)>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="t-muted p-6 text-center text-sm">
                                        No modules yet. Add rows to IWMS_Modules (see seed-modules.sql) and reload this page.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (! $locked && $modules->isNotEmpty())
                    <div class="flex justify-end gap-2 border-t t-divider px-5 py-3">
                        <a href="{{ route('module-access.index', ['type' => $typeId]) }}"
                           class="t-text inline-flex h-9 items-center rounded-lg border t-ring px-4 text-sm font-semibold transition hover:bg-[color:var(--hover)]">
                            Discard changes
                        </a>
                        <button type="submit"
                                class="h-9 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                            Save access
                        </button>
                    </div>
                @endif
            </form>

        </div>
    </div>

    <script>
    (function () {
        document.querySelectorAll('[data-row]').forEach(function (tr) {
            const boxes = Array.from(tr.querySelectorAll('input[data-action]'));
            const all   = tr.querySelector('input[data-all]');
            const view  = tr.querySelector('input[data-action="view"]');

            const syncAll = () => { all.checked = boxes.every(b => b.checked); };
            syncAll();

            boxes.forEach(function (b) {
                b.addEventListener('change', function () {
                    if (b === view && !view.checked) {
                        boxes.forEach(x => x.checked = false);        // no view = nothing else makes sense
                    } else if (b.checked) {
                        view.checked = true;                          // create/edit/delete need view
                    }
                    syncAll();
                });
            });

            all.addEventListener('change', () => boxes.forEach(x => x.checked = all.checked));
        });
    })();
    </script>
</x-app-layout>