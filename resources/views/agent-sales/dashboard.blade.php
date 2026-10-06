@php
    // Real data comes from AgentSalesController: $agents, $totals, $startDate, $endDate, $routeOptions, $vesselOptions, $agentOptions
    $selected = [
        'vessel' => request('vessel_name', 'ALL'),
        'route'  => request('route', 'ALL'),
        'agent'  => request('agent_name', 'ALL'),
    ];

    $rangeLabel = \Carbon\Carbon::parse($startDate)->format('M-d-Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('M-d-Y');

    // shared glass style (same as the Sales Dashboard)
    $glass  = 'glass rounded-2xl';
    $select = 't-input mt-1 block h-9 w-full rounded-lg px-3 text-sm [&>option]:text-slate-900';
    $mini   = 't-input block h-9 rounded-lg px-3 text-sm [&>option]:text-slate-900';
    $expandIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>';

    // keeps the bars readable: on narrow screens the chart scrolls sideways instead of squeezing the bars
    $allChartMinWidth = max(0, count($agents)) * 26;
@endphp

<x-app-layout>
    {{-- Layout rules for this page only. Plain CSS, so it works without rebuilding Tailwind. --}}
    <style>
        .as-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
        .as-grid > * { min-width: 0; }
        .as-filters { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .as-filters input, .as-filters select { min-width: 0; }
        .as-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; }
        .as-ctrls { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .as-sel { width: 9rem; }
        .as-num { width: 4.5rem; text-align: center; }
        .as-scrollx { overflow-x: auto; }
        .as-chart { position: relative; height: 18rem; }
        .as-scroll { max-height: 28rem; overflow: auto; }
        .as-table { width: 100%; min-width: 44rem; text-align: left; font-size: .875rem; }

        @media (max-width: 639px) {
            .as-ctrls { width: 100%; }
            .as-ctrls .as-sel { flex: 1 1 auto; width: auto; }
        }
        @media (min-width: 640px) {
            .as-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (min-width: 1024px) {
            .as-grid { grid-template-columns: repeat(12, minmax(0, 1fr)); }
            .as-span-6  { grid-column: span 6 / span 6; }
            .as-span-12 { grid-column: span 12 / span 12; }
            .as-filters { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        }
    </style>

    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight tracking-tight t-text">
            {{ __('Agent Sales Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="as-grid max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" onsubmit="window.Loader && Loader.show('Loading result…')"
                  class="{{ $glass }} as-span-12 p-5">
                <div class="as-filters">
                    <label class="block t-muted text-[13px] font-medium">
                        Start date
                        <input type="date" name="start_date" value="{{ $startDate }}" class="{{ $select }}">
                    </label>
                    <label class="block t-muted text-[13px] font-medium">
                        End date
                        <input type="date" name="end_date" value="{{ $endDate }}" class="{{ $select }}">
                    </label>
                    <label class="block t-muted text-[13px] font-medium">
                        Vessel Name
                        <select name="vessel_name" class="{{ $select }}">
                            @foreach ($vesselOptions as $o)
                                <option @selected($selected['vessel'] === $o)>{{ $o }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block t-muted text-[13px] font-medium">
                        Route
                        <select name="route" class="{{ $select }}">
                            @foreach ($routeOptions as $o)
                                <option @selected($selected['route'] === $o)>{{ $o }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block t-muted text-[13px] font-medium">
                        Agent Name
                        <select name="agent_name" class="{{ $select }}">
                            @foreach ($agentOptions as $o)
                                <option @selected($selected['agent'] === $o)>{{ $o }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex items-end">
                        <button type="submit"
                                class="h-9 w-full rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                            Load
                        </button>
                    </div>
                </div>
            </form>

            {{-- Agent Sales (all agents) --}}
            <div class="{{ $glass }} as-span-6 p-5">
                <div class="as-head">
                    <h3 class="text-sm font-bold">Agent Sales <span id="allTitleMetric">(Sales)</span></h3>
                    <div class="as-ctrls text-xs t-muted">
                        Show Data By
                        <select id="allMetric" onchange="drawAll()" class="{{ $mini }} as-sel">
                            <option value="sales">Sales</option><option value="pax">Pax Count</option><option value="cargo">Cargo Count</option>
                        </select>
                    </div>
                </div>
                <div class="as-scrollx mt-3">
                    <div class="as-chart" style="min-width: {{ $allChartMinWidth }}px">
                        <canvas id="allChart"></canvas>
                        <div id="allEmpty" class="t-muted pointer-events-none absolute inset-0 hidden items-center justify-center text-base font-bold">No Data</div>
                    </div>
                </div>
                <div class="mt-1 flex justify-end">
                    <button type="button" onclick="expandChart('all')" title="Expand" aria-label="Expand chart"
                            class="t-muted rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">{!! $expandIcon !!}</button>
                </div>
            </div>

            {{-- Top Agents --}}
            <div class="{{ $glass }} as-span-6 p-5">
                <div class="as-head">
                    <h3 class="text-sm font-bold">Top <span id="topTitleN">3</span> Agents <span id="topTitleMetric">(Sales)</span></h3>
                    <div class="as-ctrls text-xs t-muted">
                        Top
                        <input id="topN" type="number" min="1" max="50" value="3" oninput="drawTop()"
                               class="{{ $mini }} as-num" aria-label="How many top agents to show">
                        Show Data By
                        <select id="topMetric" onchange="drawTop()" class="{{ $mini }} as-sel">
                            <option value="sales">Sales</option><option value="pax">Pax Count</option><option value="cargo">Cargo Count</option>
                        </select>
                    </div>
                </div>
                <div class="as-chart mt-3">
                    <canvas id="topChart"></canvas>
                    <div id="topEmpty" class="t-muted pointer-events-none absolute inset-0 hidden items-center justify-center text-base font-bold">No Data</div>
                </div>
                <div class="mt-1 flex justify-end">
                    <button type="button" onclick="expandChart('top')" title="Expand" aria-label="Expand chart"
                            class="t-muted rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">{!! $expandIcon !!}</button>
                </div>
            </div>

            {{-- Sales Table Summary --}}
            <div class="{{ $glass }} as-span-12 overflow-hidden">
                <div class="as-head px-5 py-3">
                    <h3 class="text-sm font-bold">Sales Table Summary</h3>
                    <button type="button" onclick="exportExcel()" @disabled(! count($agents))
                            class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-sky-500 disabled:pointer-events-none disabled:opacity-40 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg>
                        Export To Excel
                    </button>
                </div>

                <div class="">
                    <table class="as-table">
                        <thead class="sticky top-0 bg-blue-950 text-white">
                            <tr>
                                <th class="px-4 py-3 font-bold">Vessel Name</th>
                                <th class="px-4 py-3 font-bold">Trip Date Range</th>
                                <th class="px-4 py-3 font-bold">Agent Name</th>
                                <th class="px-4 py-3 text-right font-bold">Gross Sales</th>
                                <th class="px-4 py-3 text-right font-bold">NET Sales</th>
                                <th class="px-4 py-3 text-right font-bold">Pax</th>
                                <th class="px-4 py-3 text-right font-bold">Cargo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agents as $a)
                                <tr class="border-b t-divider transition hover:bg-[color:var(--hover)]">
                                    <td class="whitespace-nowrap px-4 py-2.5">{{ $selected['vessel'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 t-muted">{{ $rangeLabel }}</td>
                                    <td class="px-4 py-2.5 font-semibold">{{ $a['name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right">{{ number_format($a['gross'], 2) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right">{{ number_format($a['sales'], 2) }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ number_format($a['pax']) }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ number_format($a['cargo']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if (count($agents))
                            <tfoot>
                                <tr class="font-bold">
                                    <td class="px-4 py-3" colspan="3">Total ({{ number_format($totals['agents']) }} agents)</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ number_format($totals['gross'], 2) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ number_format($totals['sales'], 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($totals['pax']) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($totals['cargo']) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                    @unless (count($agents))
                        <p class="t-muted p-6 text-center text-sm">No sales found for the selected filters.</p>
                    @endunless
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const agents      = @json($agents);
        const totals      = @json($totals);
        const rangeLabel  = @json($rangeLabel);
        const vesselLabel = @json($selected['vessel']);
        const fileRange   = @json($startDate . '_to_' . $endDate);
        const metricLabel = { sales: 'Sales', pax: 'Pax Count', cargo: 'Cargo Count' };

        const css     = v => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
        const isLight = () => document.documentElement.getAttribute('data-theme') === 'light';
        const base    = { responsive: true, maintainAspectRatio: false };
        const el      = id => document.getElementById(id);

        function applyDefaults() {
            Chart.defaults.color = css('--muted');
            Chart.defaults.borderColor = css('--grid');
            Chart.defaults.font.family = "'Nunito', sans-serif";
        }
        const mount = (id, cfg) => { Chart.getChart(id)?.destroy(); return new Chart(id, cfg); };
        function showEmpty(id, empty) {
            el(id).classList.toggle('hidden', !empty);
            el(id).classList.toggle('flex', empty);
        }

        const fmt   = (metric, v) => metric === 'sales'
            ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : Number(v).toLocaleString('en-US');
        const short = (metric, v) => metric !== 'sales' ? v : (v >= 1e6 ? (v / 1e6) + 'M' : v >= 1e3 ? (v / 1e3) + 'k' : v);
        const ranked = metric => agents.filter(a => a[metric] > 0).sort((a, b) => b[metric] - a[metric]);
        const topN   = () => Math.max(1, Math.min(50, parseInt(el('topN').value, 10) || 3));

        /* ---------- one config builder per chart: used by the card AND the fullscreen view ---------- */
        function barConfig(rows, metric, big, labelLen) {
            const empty = !rows.length;
            return { empty, config: {
                type: 'bar',
                data: { labels: rows.map(r => r.name),
                        datasets: [{ data: rows.map(r => r[metric]), borderRadius: 4,
                                     backgroundColor: isLight() ? '#1f7ac9' : '#38bdf8' }] },
                options: { ...base,
                           plugins: { legend: { display: false },
                                      tooltip: { callbacks: { label: ctx => ` ${metricLabel[metric]}: ${fmt(metric, ctx.parsed.y)}` } } },
                           scales: { x: { display: !empty, grid: { display: false },
                                          ticks: { maxRotation: 60, minRotation: labelLen ? 60 : 0, font: { size: big ? 12 : 10 },
                                                   callback(v) { const l = this.getLabelForValue(v);
                                                                 return labelLen && l.length > labelLen ? l.slice(0, labelLen - 1) + '…' : l; } } },
                                     y: { display: !empty, beginAtZero: true, ticks: { callback: v => short(metric, v) } } } }
            } };
        }
        const allConfig = big => { const m = el('allMetric').value; return barConfig(ranked(m), m, big, big ? 0 : 16); };
        const topConfig = big => { const m = el('topMetric').value; return barConfig(ranked(m).slice(0, topN()), m, big, 0); };

        /* ---------- cards ---------- */
        function drawAll() {
            applyDefaults();
            el('allTitleMetric').textContent = '(' + el('allMetric').selectedOptions[0].text + ')';
            const c = allConfig(false);
            showEmpty('allEmpty', c.empty);
            mount('allChart', c.config);
            if (expanded === 'all') redrawExpanded();
        }
        function drawTop() {
            applyDefaults();
            el('topTitleN').textContent = topN();
            el('topTitleMetric').textContent = '(' + el('topMetric').selectedOptions[0].text + ')';
            const c = topConfig(false);
            showEmpty('topEmpty', c.empty);
            mount('topChart', c.config);
            if (expanded === 'top') redrawExpanded();
        }
        function drawCharts() { drawAll(); drawTop(); }

        /* ---------- fullscreen view ---------- */
        let expanded = null;                        // 'all' | 'top' | null

        function expandChart(kind) {
            expanded = kind;
            const m = el('expandModal');
            m.classList.remove('hidden'); m.classList.add('flex');
            document.body.style.overflow = 'hidden';
            redrawExpanded();
        }
        function redrawExpanded() {
            if (!expanded) return;
            applyDefaults();
            const isTop = expanded === 'top';
            const sel   = el(isTop ? 'topMetric' : 'allMetric');
            el('expandMetric').value = sel.value;
            el('expandTopWrap').classList.toggle('hidden', !isTop);
            el('expandTopWrap').classList.toggle('flex', isTop);
            if (isTop) el('expandTopN').value = el('topN').value;

            const c = isTop ? topConfig(true) : allConfig(true);
            el('expandTitle').textContent = (isTop ? `Top ${topN()} Agents` : 'Agent Sales') + ` (${rangeLabel}) ${sel.selectedOptions[0].text}`;
            showEmpty('expandEmpty', c.empty);
            mount('expandCanvas', c.config);
        }
        function closeExpand() {
            expanded = null;
            const m = el('expandModal');
            m.classList.add('hidden'); m.classList.remove('flex');
            Chart.getChart('expandCanvas')?.destroy();
            document.body.style.overflow = '';
        }
        // the fullscreen controls drive the card controls, then everything redraws
        function expandMetricChanged() {
            el(expanded === 'top' ? 'topMetric' : 'allMetric').value = el('expandMetric').value;
            expanded === 'top' ? drawTop() : drawAll();
        }
        function expandTopNChanged() {
            el('topN').value = el('expandTopN').value;
            drawTop();
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && expanded) closeExpand(); });

        /* ---------- Export to Excel (built in the browser from the rows already on the page) ---------- */
        let xlsxLoader;
        function loadXlsx() {
            xlsxLoader ??= new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
                s.onload = resolve;
                s.onerror = () => { xlsxLoader = null; reject(new Error('Could not load the Excel library')); };
                document.head.appendChild(s);
            });
            return xlsxLoader;
        }
        async function exportExcel() {
            if (!agents.length) return;
            window.Loader && Loader.show('Preparing file…');
            try {
                await loadXlsx();
                const header = ['Vessel Name', 'Trip Date Range', 'Agent Name', 'Gross Sales', 'NET Sales', 'Pax', 'Cargo'];
                const data   = agents.map(a => [vesselLabel, rangeLabel, a.name, a.gross, a.sales, a.pax, a.cargo]);
                data.push(['Total', '', '', totals.gross, totals.sales, totals.pax, totals.cargo]);

                const ws = XLSX.utils.aoa_to_sheet([header, ...data]);
                ws['!cols'] = [{ wch: 16 }, { wch: 30 }, { wch: 34 }, { wch: 16 }, { wch: 16 }, { wch: 10 }, { wch: 10 }];
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Agent Sales');
                XLSX.writeFile(wb, `Agent-Sales_${fileRange}.xlsx`);
            } catch (e) {
                console.error('Export failed:', e);
                alert('Could not create the file. Please check your internet connection and try again.');
            } finally {
                window.Loader && Loader.hide();
            }
        }

        drawCharts();
        window.addEventListener('themechange', drawCharts);
    </script>

    {{-- Fullscreen chart view --}}
    <div id="expandModal" class="fixed inset-0 z-[60] hidden items-center justify-center p-3 sm:p-6"
         role="dialog" aria-modal="true" aria-labelledby="expandTitle">
        <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" onclick="closeExpand()"></div>

        <div class="glass relative flex h-[92vh] w-full max-w-[96rem] flex-col overflow-hidden rounded-2xl">
            <div class="as-head px-5 py-3">
                <h3 id="expandTitle" class="text-base font-bold sm:text-lg"></h3>

                <div class="as-ctrls">
                    <div id="expandTopWrap" class="t-muted hidden items-center gap-2 text-xs">
                        Top
                        <input id="expandTopN" type="number" min="1" max="50" oninput="expandTopNChanged()"
                               class="{{ $mini }} as-num" aria-label="How many top agents to show">
                    </div>
                    <select id="expandMetric" onchange="expandMetricChanged()" class="{{ $mini }} as-sel">
                        <option value="sales">Sales</option><option value="pax">Pax Count</option><option value="cargo">Cargo Count</option>
                    </select>
                    <button type="button" onclick="closeExpand()" aria-label="Close"
                            class="t-text rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
            </div>

            <div class="relative min-h-0 flex-1 px-5 pb-5">
                <canvas id="expandCanvas"></canvas>
                <div id="expandEmpty" class="t-muted pointer-events-none absolute inset-0 hidden items-center justify-center text-lg font-bold">No Data</div>
            </div>
        </div>
    </div>
</x-app-layout>