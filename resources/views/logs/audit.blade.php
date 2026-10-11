@php
    $glass  = 'glass rounded-2xl';
    $select = 't-input mt-1 block h-9 w-full rounded-lg px-3 text-sm [&>option]:text-slate-900';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight tracking-tight t-text">
            {{ __('Audit Trail Logs') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto space-y-4 px-4 sm:px-6 lg:px-8">

            {{-- Overview for the selected dates --}}
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ([['Total events', $stats['total']], ['Logins', $stats['logins']], ['Failed logins', $stats['failed']], ['Data changes', $stats['changes']]] as [$label, $value])
                    <div class="{{ $glass }} p-4">
                        <p class="t-muted text-xs font-medium">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-extrabold tracking-tight">{{ number_format($value) }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('logs.audit') }}" class="{{ $glass }} p-5">
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
                    <label class="t-muted block text-[13px] font-medium">From
                        <input type="date" name="start_date" value="{{ $f['start'] }}" class="{{ $select }}">
                    </label>
                    <label class="t-muted block text-[13px] font-medium">To
                        <input type="date" name="end_date" value="{{ $f['end'] }}" class="{{ $select }}">
                    </label>
                    <label class="t-muted block text-[13px] font-medium">Event
                        <select name="event" class="{{ $select }}">
                            <option value="">All events</option>
                            @foreach ($options['events'] as $e)
                                <option value="{{ $e }}" @selected($f['event'] === $e)>{{ ucfirst(str_replace('_', ' ', $e)) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="t-muted block text-[13px] font-medium">Module / Table
                        <select name="table" class="{{ $select }}">
                            <option value="">All</option>
                            @foreach ($options['tables'] as $t)
                                <option value="{{ $t }}" @selected($f['table'] === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="t-muted block text-[13px] font-medium">User
                        <select name="user" class="{{ $select }}">
                            <option value="0">All users</option>
                            @foreach ($options['users'] as $u)
                                <option value="{{ $u['id'] }}" @selected($f['user'] === $u['id'])>{{ $u['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="t-muted block text-[13px] font-medium">Rows per page
                        <select name="per_page" class="{{ $select }}">
                            @foreach ([25, 50, 100] as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="t-muted col-span-2 block text-[13px] font-medium md:col-span-2 xl:col-span-3">Search
                        <input type="text" name="q" value="{{ $f['q'] }}" placeholder="Description, user, IP, table or record id…" class="{{ $select }}">
                    </label>
                    <div class="col-span-2 flex items-end gap-2 md:col-span-2 xl:col-span-3 xl:justify-end">
                        <button type="submit" class="t-btn h-9 rounded-lg px-5 text-sm font-semibold">Apply</button>
                        <a href="{{ route('logs.audit') }}" class="t-muted inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold transition hover:bg-[color:var(--hover)]">Reset</a>
                        <a href="{{ route('logs.audit.export', request()->query()) }}" data-no-loader
                           class="t-text inline-flex h-9 items-center gap-1.5 rounded-lg border t-divider px-3 text-sm font-semibold transition hover:bg-[color:var(--hover)]">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg>
                            Export CSV
                        </a>
                    </div>
                </div>
            </form>

            {{-- Log table --}}
            <div class="{{ $glass }} overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-left text-sm">
                        <thead class="bg-blue-950 text-white">
                            <tr>
                                <th class="px-4 py-3 font-bold">Date &amp; Time</th>
                                <th class="px-4 py-3 font-bold">User</th>
                                <th class="px-4 py-3 font-bold">Event</th>
                                <th class="px-4 py-3 font-bold">Module</th>
                                <th class="px-4 py-3 font-bold">Record</th>
                                <th class="px-4 py-3 font-bold">Description</th>
                                <th class="px-4 py-3 font-bold">IP</th>
                                <th class="px-4 py-3 text-center font-bold">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr class="border-b t-divider transition hover:bg-[color:var(--hover)]">
                                    <td class="whitespace-nowrap px-4 py-2.5">{{ $r['time'] }}</td>
                                    <td class="px-4 py-2.5">{{ $r['user'] }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-bold {{ $r['badge'] }}">{{ $r['event_label'] }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">{{ $r['table'] ?? '—' }}</td>
                                    <td class="px-4 py-2.5">{{ $r['record'] ?? '—' }}</td>
                                    <td class="max-w-xs truncate px-4 py-2.5" title="{{ $r['description'] }}">{{ $r['description'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5">{{ $r['ip'] ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button type="button" onclick="showAudit({{ $r['id'] }})"
                                                class="t-accent rounded-lg px-2 py-1 text-xs font-semibold hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">View</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="t-muted px-4 py-10 text-center">No audit entries for these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs">
                    <span class="t-muted">Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ number_format($logs->total()) }}</span>
                    <div class="flex items-center gap-2">
                        <span class="t-muted">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
                        @foreach ([['Previous', $logs->previousPageUrl()], ['Next', $logs->nextPageUrl()]] as [$text, $url])
                            @if ($url)
                                <a href="{{ $url }}" class="t-text rounded-lg border t-divider px-3 py-1.5 font-semibold transition hover:bg-[color:var(--hover)]">{{ $text }}</a>
                            @else
                                <span class="t-muted rounded-lg border t-divider px-3 py-1.5 font-semibold opacity-40">{{ $text }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Details popup --}}
    <div id="auditModal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="auditTitle">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeAudit()"></div>

        <div class="glass relative flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl">
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <h3 id="auditTitle" class="text-base font-bold">Audit entry</h3>
                <button type="button" onclick="closeAudit()" aria-label="Close"
                        class="t-text rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="space-y-4 overflow-auto px-5 pb-5">
                <dl id="auditMeta" class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2"></dl>

                <div>
                    <h4 class="mb-2 text-sm font-bold">What changed</h4>
                    <div id="auditDiff" class="overflow-x-auto"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const rows  = @json($rows);
        const byId  = {};
        rows.forEach(r => byId[r.id] = r);

        const modal = document.getElementById('auditModal');
        const esc   = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const dash  = '<span class="t-muted">—</span>';

        function show(v) {
            if (v === null || v === undefined || v === '') return dash;
            if (v === '[hidden]') return '<em class="t-muted">hidden</em>';
            if (typeof v === 'object') return '<code class="text-xs">' + esc(JSON.stringify(v)) + '</code>';
            return esc(v);
        }

        function meta(label, value) {
            return `<div><dt class="t-muted text-xs font-medium">${label}</dt><dd class="break-words">${value}</dd></div>`;
        }

        window.showAudit = function (id) {
            const r = byId[id];
            if (!r) return;

            document.getElementById('auditTitle').textContent = `${r.event_label} · #${r.id}`;
            document.getElementById('auditMeta').innerHTML =
                meta('Date & time', esc(r.time)) +
                meta('User', esc(r.user)) +
                meta('Event', `<span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-bold ${r.badge}">${esc(r.event_label)}</span>`) +
                meta('Module / table', show(r.table)) +
                meta('Record', show(r.record)) +
                meta('IP address', show(r.ip)) +
                meta('Request', r.method ? esc(r.method) + ' ' + show(r.url) : dash) +
                meta('Browser', show(r.agent)) +
                (r.description ? `<div class="sm:col-span-2">${meta('Description', esc(r.description))}</div>` : '');

            const oldV = r.old || {}, newV = r.new || {};
            const keys = [...new Set([...Object.keys(oldV), ...Object.keys(newV)])];
            document.getElementById('auditDiff').innerHTML = keys.length
                ? `<table class="w-full text-left text-sm">
                     <thead><tr class="t-muted text-xs"><th class="py-1 pe-3 font-semibold">Field</th><th class="py-1 pe-3 font-semibold">Before</th><th class="py-1 font-semibold">After</th></tr></thead>
                     <tbody>${keys.map(k => {
                        const changed = JSON.stringify(oldV[k]) !== JSON.stringify(newV[k]);
                        return `<tr class="border-t t-divider ${changed ? 'font-semibold' : ''}">
                                  <td class="py-1.5 pe-3 align-top">${esc(k)}</td>
                                  <td class="py-1.5 pe-3 align-top">${show(oldV[k])}</td>
                                  <td class="py-1.5 align-top">${show(newV[k])}</td></tr>`;
                     }).join('')}</tbody></table>`
                : '<p class="t-muted text-sm">No field details were recorded for this event.</p>';

            modal.classList.remove('hidden'); modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        };

        window.closeAudit = function () {
            modal.classList.add('hidden'); modal.classList.remove('flex');
            document.body.style.overflow = '';
        };
        document.addEventListener('keydown', e => { if (e.key === 'Escape') window.closeAudit(); });
    })();
    </script>
</x-app-layout>