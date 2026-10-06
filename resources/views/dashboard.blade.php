@php
    // ---------- Real data comes from DashboardController:
    //            $routeOptions, $vesselOptions, $channelOptions, $selectedMonth, $cancelledTrips, $passenger, $vehicle, $channelSales, $topTrips, $forecast ----------
    // ---------- The blocks below are still STATIC: replace each with a DataService call later ----------
    $filters = [
        'Month'       => ['January','February','March','April','May','June','July','August','September','October','November','December'],
        'Year'        => array_map('strval', range(now('Asia/Manila')->year, now('Asia/Manila')->year - 3)),
        'Route'       => $routeOptions,
        'Vessel Name' => $vesselOptions,
        'Channel'     => $channelOptions,
    ];
    $selected = [
        'Month'       => $selectedMonth,
        'Year'        => request('year', (string) now('Asia/Manila')->year),
        'Route'       => request('route', 'ALL'),
        'Vessel Name' => request('vessel_name', 'All'),
        'Channel'     => request('channel', 'All'),
    ];

    // filters printed on the exported PDF / Excel
    $exportMeta = [
        'month'  => $selected['Month'],
        'year'   => $selected['Year'],
        'route'  => $selected['Route'],
        'vessel' => $selected['Vessel Name'],
    ];

    $periodLabel = $selected['Month'] . ' - ' . $selected['Year'];   // used in the fullscreen chart titles

    // shared glass style
    $glass = 'glass rounded-2xl';
    $select = 't-input mt-1 block h-9 w-full rounded-lg px-3 text-sm [&>option]:text-slate-900';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight tracking-tight t-text">
            {{ __('Sales Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto grid gap-4 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">

            {{-- Filters (the loader shows while the page reloads with the new filter) --}}
            <form method="GET" onsubmit="window.Loader && Loader.show('Loading result…')"
                class="{{ $glass }} p-5 lg:col-span-8">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach ($filters as $name => $options)
                        <label class="block t-muted text-[13px] font-medium">
                            {{ $name }}
                            <select name="{{ strtolower(str_replace(' ', '_', $name)) }}"
                                    class="{{ $select }}">
                                @foreach ($options as $o)
                                    <option @selected($selected[$name] === $o)>{{ $o }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach

                    {{-- Fills the empty 6th grid cell, next to Channel --}}
                    <div class="flex items-end">
                        <button type="submit"
                                class="h-9 w-full rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                            Load
                        </button>
                    </div>
                </div>
            </form>

            {{-- Cancelled trips (real data from the controller) --}}
            <div class="{{ $glass }} flex flex-col items-center justify-center p-5 text-center lg:col-span-4">
                <p class="text-sm font-bold">Total Cancelled Trips</p>
                <div class="mt-3 w-full max-w-[14rem] rounded-full border-2 t-ring bg-white/5 px-6 py-3">
                    <p class="text-3xl font-extrabold">{{ number_format($cancelledTrips) }}</p>
                    <p class="text-[11px] t-muted">Cancelled Trips</p>
                </div>
                <a href="#" onclick="openCancelled(); return false;" data-no-loader class="mt-2 text-xs font-medium t-accent hover:underline">See Details</a>
            </div>

            {{-- Passenger & Vehicle (real data from the controller) --}}
            @foreach ([['Passenger', $passenger], ['Vehicle', $vehicle]] as [$title, $d])
                <div class="{{ $glass }} p-5 lg:col-span-3">
                    <h3 class="text-center text-base font-bold">{{ $title }}</h3>
                    <div class="mx-auto mt-4 w-full max-w-[12rem] rounded-[2rem] border-2 t-ring bg-white/5 px-4 py-5 text-center">
                        <p class="text-4xl font-extrabold tracking-tight">{{ number_format($d['total']) }}</p>
                        <p class="text-[11px] t-muted">{{ $d['label'] }}</p>
                    </div>
                    <dl class="mt-5 space-y-1.5 border-t t-divider pt-3 text-xs">
                        @foreach ($d['stats'] as $k => $v)
                            <div class="flex justify-between gap-2">
                                <dt class="t-muted">{{ $k }}</dt>
                                <dd class="font-bold">{{ $v }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach

            {{-- Sales by channel --}}
            <div class="{{ $glass }} p-5 lg:col-span-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold">Sales By Channel ({{ $selected['Month'] }} - {{ $selected['Year'] }})</h3>
                    <div class="flex items-center gap-2 text-xs t-muted">
                        Show Data By
                        <select id="channelMetric" onchange="channelMetricChanged()" class="{{ $select }} !mt-0 !w-36"><option value="sales">Sales</option><option value="pax">Pax Count</option><option value="cargo">Cargo Count</option></select>
                    </div>
                </div>
                <div class="relative mt-3 h-64">
                    <canvas id="channelChart"></canvas>
                    {{-- shown by drawChannelChart() when there is nothing to plot --}}
                    <div id="channelEmpty" class="t-muted pointer-events-none absolute inset-0 hidden items-center justify-center text-base font-bold">No Data</div>
                </div>
                <div class="mt-1 flex justify-end">
                    <button type="button" onclick="expandChart('channel')" title="Expand" aria-label="Expand chart"
                            class="t-muted rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                    </button>
                </div>
            </div>

            {{-- Top trips per route --}}
            <div class="{{ $glass }} p-5 lg:col-span-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold">Top Trips Per Route ({{ $selected['Month'] }} - {{ $selected['Year'] }}) <span id="tripsTitleMetric">Sales</span></h3>
                    <div class="flex items-center gap-2 text-xs t-muted">
                        Show Data By
                        <select id="tripsMetric" onchange="tripsMetricChanged()" class="{{ $select }} !mt-0 !w-36"><option value="sales">Sales</option><option value="pax">Pax Count</option><option value="cargo">Cargo Count</option></select>
                    </div>
                </div>
                <div class="relative mt-3 h-72">
                    <canvas id="tripsChart"></canvas>
                    {{-- shown by drawTripsChart() when there is nothing to plot --}}
                    <div id="tripsEmpty" class="t-muted pointer-events-none absolute inset-0 hidden items-center justify-center text-base font-bold">No Data</div>
                </div>
                {{-- clickable time legend: click a time to cross it out / bring it back --}}
                <div id="tripsLegend" class="mt-3 flex flex-wrap justify-center gap-x-2 gap-y-1"></div>
                <div class="mt-1 flex justify-end">
                    <button type="button" onclick="expandChart('trips')" title="Expand" aria-label="Expand chart"
                            class="t-muted rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                    </button>
                </div>
            </div>

            {{-- Sales forecast --}}
            <div class="{{ $glass }} p-5 lg:col-span-6">
                <h3 class="text-sm font-bold">Monthly Sales Graph</h3>
                <div class="relative mt-3 h-72">
                    <canvas id="forecastChart"></canvas>
                    {{-- shown by drawForecastChart() when there is nothing to plot --}}
                    <div id="forecastEmpty" class="t-muted pointer-events-none absolute inset-0 hidden items-center justify-center text-base font-bold">No Data</div>
                </div>
                <div class="mt-1 flex justify-end">
                    <button type="button" onclick="expandChart('forecast')" title="Expand" aria-label="Expand chart"
                            class="t-muted rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const channel  = @json($channelSales);
        const trips    = @json($topTrips);
        const forecast = @json($forecast);
        const period   = @json($periodLabel);
        const months   = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const css      = v => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
        const isLight  = () => document.documentElement.getAttribute('data-theme') === 'light';
        const base     = { responsive: true, maintainAspectRatio: false };
        const tickOpts = big => ({ maxRotation: 60, minRotation: 60, font: { size: big ? 12 : 10 } });

        function applyDefaults() {
            Chart.defaults.color = css('--muted');
            Chart.defaults.borderColor = css('--grid');
            Chart.defaults.font.family = "'Nunito', sans-serif";
        }
        const mount = (id, cfg) => { Chart.getChart(id)?.destroy(); return new Chart(id, cfg); };
        function showEmpty(id, empty) {
            const n = document.getElementById(id);
            n.classList.toggle('hidden', !empty);
            n.classList.toggle('flex', empty);
        }

        /* ---------- one config builder per chart: used by the small card AND the fullscreen view ---------- */

        function channelConfig(big) {
            const light   = isLight();
            const metric  = document.getElementById('channelMetric').value;         // 'sales' | 'pax' | 'cargo'
            const palette = light ? ['#0b3d91', '#1f7ac9', '#7dd3fc', '#38bdf8', '#94a3b8', '#cbd5e1']
                                  : ['#0ea5e9', '#e0f2fe', '#67e8f9', '#38bdf8', '#94a3b8', '#cbd5e1'];
            // keep only channels that have something for this metric (colour stays tied to the channel)
            const slices = channel.labels
                .map((label, i) => ({ label, value: (channel[metric] || [])[i] || 0, color: palette[i % palette.length] }))
                .filter(s => s.value > 0);
            const total = slices.reduce((a, s) => a + s.value, 0);
            const empty = !slices.length;
            const fmt   = v => metric === 'sales'
                ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : Number(v).toLocaleString('en-US');

            return { empty, config: {
                type: 'pie',
                data: empty
                    ? { labels: ['No Data'], datasets: [{ data: [1], backgroundColor: [light ? '#e2e8f0' : 'rgba(255,255,255,.12)'], borderWidth: 0 }] }
                    : { labels: slices.map(s => `${s.label} ${(s.value / total * 100).toFixed(2)}%`),
                        datasets: [{ data: slices.map(s => s.value), backgroundColor: slices.map(s => s.color),
                                     borderColor: light ? '#ffffff' : 'rgba(30,58,138,.6)' }] },
                options: { ...base, plugins: {
                    legend: { display: !empty, position: 'bottom', labels: { font: { size: big ? 14 : 12 } } },
                    tooltip: { enabled: !empty, callbacks: { label: ctx => ` ${slices[ctx.dataIndex].label}: ${fmt(ctx.parsed)}` } } } }
            } };
        }

        function tripsConfig(big) {
            const metric = document.getElementById('tripsMetric').value;           // 'sales' | 'pax' | 'cargo'
            const all    = trips[metric] || [];
            const times  = tripTimes();
            // keep only the bars whose time is not crossed out in the legend
            const idx    = all.map((_, i) => i).filter(i => !hiddenTimes.has(trips.times[i]));
            const values = idx.map(i => all[i]);
            const empty  = !values.some(v => v > 0);
            const label  = { sales: 'Sales', pax: 'Pax', cargo: 'Cargo' }[metric];
            const fmt    = v => metric === 'sales'
                ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : Number(v).toLocaleString('en-US');

            return { empty, config: {
                type: 'bar',
                data: { labels: empty ? [] : idx.map(i => trips.labels[i]),
                        datasets: [{ data: empty ? [] : values,
                                     backgroundColor: idx.map(i => timeColor(times.indexOf(trips.times[i]), times.length)),
                                     borderRadius: 4 }] },
                options: { ...base,
                           plugins: { legend: { display: false },
                                      tooltip: { callbacks: { label: ctx => { const i = idx[ctx.dataIndex];
                                          return [ ` ${label}: ${fmt(ctx.parsed.y)}`, ` Departures: ${trips.schedules[i]}`, ` Vessel: ${trips.vessels[i]}` ]; } } } },
                           scales: { x: { display: !empty, ticks: tickOpts(big), grid: { display: false } },
                                     y: { display: !empty, beginAtZero: true, ticks: { callback: v => metric === 'sales' ? (v / 1000) + 'k' : v } } } }
            } };
        }

        /* ---------- clickable time legend for the bar chart ---------- */
        const hiddenTimes = new Set();                                  // times crossed out by the user
        const tripTimes   = () => [...new Set(trips.times || [])];      // unique times, ascending (as sent by the server)
        function timeColor(i, n) {                                      // dark -> light across the day
            const t = n > 1 ? i / (n - 1) : 0;
            return isLight() ? `hsl(212, 80%, ${30 + t * 32}%)` : `hsl(200, 85%, ${55 + t * 25}%)`;
        }
        function renderTripsLegend(id) {
            const box = document.getElementById(id);
            const times = tripTimes();
            box.innerHTML = '';
            times.forEach((t, i) => {
                const off = hiddenTimes.has(t);
                const b = document.createElement('button');
                b.type = 'button';
                b.title = off ? 'Click to show' : 'Click to hide';
                b.className = 't-muted inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300' + (off ? ' line-through opacity-50' : '');
                const dot = document.createElement('span');
                dot.className = 'h-2.5 w-2.5 rounded-full';
                dot.style.background = timeColor(i, times.length);
                const txt = document.createElement('span');
                txt.textContent = t;
                b.append(dot, txt);
                b.onclick = () => toggleTime(t);
                box.appendChild(b);
            });
        }
        function toggleTime(t) {
            hiddenTimes.has(t) ? hiddenTimes.delete(t) : hiddenTimes.add(t);
            drawTripsChart();
            if (expanded === 'trips') redrawExpanded();
        }

        function forecastConfig(big) {
            // newest year gets the first colour, so the selected year always stands out
            const palette = isLight() ? ['#0f1f4b', '#10b981', '#eab308', '#ef4444', '#3b82f6', '#a855f7', '#f97316', '#64748b']
                                      : ['#ffffff', '#34d399', '#fde047', '#f87171', '#60a5fa', '#c084fc', '#fb923c', '#94a3b8'];
            const entries = Object.entries(forecast);                       // oldest year first
            const empty   = !entries.some(([, d]) => d.some(v => v));       // null / 0 everywhere

            return { empty, config: {
                type: 'line',
                data: { labels: empty ? [] : months,
                        datasets: empty ? [] : entries.map(([year, data], i) => {
                            const color = palette[(entries.length - 1 - i) % palette.length];
                            return { label: year, data, borderColor: color, backgroundColor: color,
                                     tension: .3, pointRadius: big ? 4 : 3, borderWidth: 2 };
                        }) },
                options: { ...base,
                           plugins: { legend: { display: !empty, position: 'bottom', labels: { font: { size: big ? 14 : 12 } } },
                                      tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${Number(ctx.parsed.y).toFixed(2)}M` } } },
                           scales: { x: { display: !empty, ticks: tickOpts(big) },
                                     y: { display: !empty, beginAtZero: true, ticks: { callback: v => v + 'M' } } } }
            } };
        }

        /* ---------- small cards ---------- */

        function drawChannelChart() {
            applyDefaults();
            const c = channelConfig(false);
            showEmpty('channelEmpty', c.empty);
            mount('channelChart', c.config);
        }
        function drawTripsChart() {
            applyDefaults();
            const c = tripsConfig(false);
            showEmpty('tripsEmpty', c.empty);
            mount('tripsChart', c.config);
            renderTripsLegend('tripsLegend');
        }
        function drawForecastChart() {
            applyDefaults();
            const c = forecastConfig(false);
            showEmpty('forecastEmpty', c.empty);
            mount('forecastChart', c.config);
        }
        function drawCharts() {
            applyDefaults();
            drawChannelChart();
            drawTripsChart();
            drawForecastChart();
            redrawExpanded();                       // keep the fullscreen view in sync (e.g. theme change)
        }

        /* ---------- fullscreen view ---------- */

        let expanded = null;                        // 'channel' | 'trips' | 'forecast' | null

        function expandChart(kind) {
            expanded = kind;
            const m = document.getElementById('expandModal');
            m.classList.remove('hidden'); m.classList.add('flex');
            document.body.style.overflow = 'hidden';
            redrawExpanded();
        }

        function redrawExpanded() {
            if (!expanded) return;
            applyDefaults();
            const metricSel = document.getElementById('channelMetric');
            const sel = document.getElementById('expandMetric');
            sel.classList.toggle('hidden', expanded !== 'channel');
            const tsel = document.getElementById('expandTripsMetric');
            tsel.classList.toggle('hidden', expanded !== 'trips');
            document.getElementById('expandLegend').classList.toggle('hidden', expanded !== 'trips');

            let c, title;
            if (expanded === 'channel') {
                sel.value = metricSel.value;
                c = channelConfig(true);
                title = `${metricSel.selectedOptions[0].text} By Channel (${period})`;
            } else if (expanded === 'trips') {
                const tm = document.getElementById('tripsMetric');
                tsel.value = tm.value;
                c = tripsConfig(true);
                renderTripsLegend('expandLegend');
                title = `Top Trips Per Route (${period}) ${tm.selectedOptions[0].text}`;
            } else {
                c = forecastConfig(true);
                title = 'Monthly Sales Graph';
            }
            document.getElementById('expandTitle').textContent = title;
            showEmpty('expandEmpty', c.empty);
            mount('expandCanvas', c.config);
        }

        function closeExpand() {
            expanded = null;
            const m = document.getElementById('expandModal');
            m.classList.add('hidden'); m.classList.remove('flex');
            Chart.getChart('expandCanvas')?.destroy();
            document.body.style.overflow = '';
        }

        // the "Show Data By" select (card or fullscreen) drives the pie
        function channelMetricChanged() { drawChannelChart(); if (expanded === 'channel') redrawExpanded(); }
        function expandMetricChanged() {
            document.getElementById('channelMetric').value = document.getElementById('expandMetric').value;
            channelMetricChanged();
        }

        // the "Show Data By" select (card or fullscreen) drives the bar chart
        function tripsMetricChanged() {
            document.getElementById('tripsTitleMetric').textContent = document.getElementById('tripsMetric').selectedOptions[0].text;
            drawTripsChart();
            if (expanded === 'trips') redrawExpanded();
        }
        function expandTripsMetricChanged() {
            document.getElementById('tripsMetric').value = document.getElementById('expandTripsMetric').value;
            tripsMetricChanged();
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape' && expanded) closeExpand(); });
        drawCharts();
        window.addEventListener('themechange', drawCharts);
    </script>

    {{-- Fullscreen chart view --}}
    <div id="expandModal" class="fixed inset-0 z-[60] hidden items-center justify-center p-3 sm:p-6"
         role="dialog" aria-modal="true" aria-labelledby="expandTitle">
        <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" onclick="closeExpand()"></div>

        <div class="glass relative flex h-[92vh] w-full max-w-[96rem] flex-col overflow-hidden rounded-2xl">
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <h3 id="expandTitle" class="text-base font-bold sm:text-lg"></h3>

                <div class="flex items-center gap-2">
                    <select id="expandMetric" onchange="expandMetricChanged()" class="{{ $select }} !mt-0 !w-36 hidden">
                        <option value="sales">Sales</option><option value="pax">Pax Count</option><option value="cargo">Cargo Count</option>
                    </select>
                    <select id="expandTripsMetric" onchange="expandTripsMetricChanged()" class="{{ $select }} !mt-0 !w-36 hidden">
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
            <div id="expandLegend" class="flex hidden shrink-0 flex-wrap justify-center gap-x-2 gap-y-1 px-5 pb-4"></div>
        </div>
    </div>

    {{-- Cancelled schedules modal (filled by the script below) --}}
    <div id="cancelledModal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="cancelledTitle">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCancelled()"></div>

        <div class="glass relative flex max-h-[85vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl">
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <h3 id="cancelledTitle" class="text-base font-bold">Cancelled Schedules</h3>

                <div class="flex items-center gap-1">
                    {{-- Download buttons (minimal) --}}
                    @foreach ([['xlsx', 'Excel', 'border-emerald-600/50 hover:border-emerald-500/80'], ['pdf', 'PDF', 'border-red-600/50 hover:border-red-500/80']] as [$type, $label, $outline])
                        <button type="button" id="cancelled-{{ $type }}" onclick="downloadCancelled('{{ $type }}')"
                                title="Download as {{ $label }}" aria-label="Download as {{ $label }}" disabled
                                class="t-text inline-flex items-center gap-1.5 rounded-lg border {{ $outline }} px-2.5 py-1.5 text-xs font-semibold transition
                                       hover:bg-[color:var(--hover)] disabled:pointer-events-none disabled:opacity-40
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/>
                            </svg>
                            <span class="hidden sm:inline">{{ $label }}</span>
                        </button>
                    @endforeach

                    <span class="mx-1 h-5 w-px bg-[color:var(--card-border)]"></span>

                    <button type="button" onclick="closeCancelled()" aria-label="Close"
                            class="t-text rounded-lg p-1.5 transition hover:bg-[color:var(--hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
            </div>

            <div class="overflow-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="sticky top-0 bg-blue-950 text-white">
                        <tr>
                            <th class="w-12 px-4 py-3 text-center font-bold">#</th>
                            <th class="px-4 py-3 text-center font-bold">Trip Sched</th>
                            <th class="px-4 py-3 text-center font-bold">Status</th>
                            <th class="px-4 py-3 text-center font-bold">Initial Name</th>
                            <th class="px-4 py-3 text-center font-bold">Vessel Name</th>
                            <th class="px-4 py-3 text-center font-bold">Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="cancelledBody"></tbody>
                </table>
                <p id="cancelledEmpty" class="t-muted hidden p-6 text-center text-sm"></p>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const modal = document.getElementById('cancelledModal');
        const body  = document.getElementById('cancelledBody');
        const empty = document.getElementById('cancelledEmpty');
        const title = document.getElementById('cancelledTitle');
        const btns  = [document.getElementById('cancelled-xlsx'), document.getElementById('cancelled-pdf')];

        // filters shown in the exported file
        const meta = @json($exportMeta);
        let rows = [];

        const escHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const loader  = { show: m => window.Loader && Loader.show(m), hide: () => window.Loader && Loader.hide() };

        function open()  { modal.classList.remove('hidden'); modal.classList.add('flex'); document.body.style.overflow = 'hidden'; }
        function close() { modal.classList.add('hidden'); modal.classList.remove('flex'); document.body.style.overflow = ''; }

        async function load() {
            loader.show('Loading cancelled schedules…');
            try {
                // window.location.search carries the current filters (month, year, route, vessel_name)
                const res = await fetch(@json(route('dashboard.cancelled')) + window.location.search,
                                        { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                rows = await res.json();

                body.innerHTML = rows.map((r, i) => `
                    <tr class="border-b t-divider transition hover:bg-[color:var(--hover)]">
                        <td class="t-muted px-4 py-2.5 text-center">${i + 1}</td>
                        <td class="whitespace-nowrap px-4 py-2.5">${escHtml(r.sched)}</td>
                        <td class="px-4 py-2.5">${escHtml(r.status)}</td>
                        <td class="whitespace-nowrap px-4 py-2.5">${escHtml(r.initial)}</td>
                        <td class="whitespace-nowrap px-4 py-2.5">${escHtml(r.vessel)}</td>
                        <td class="px-4 py-2.5">${escHtml(r.remarks)}</td>
                    </tr>`).join('');
                title.textContent = `Cancelled Schedules (${rows.length})`;
                empty.textContent = 'No cancelled schedules for the selected filters.';
                empty.classList.toggle('hidden', rows.length > 0);
            } catch (e) {
                console.error('Cancelled schedules failed:', e);
                rows = [];
                body.innerHTML = '';
                empty.textContent = 'Could not load the data. Please try again.';
                empty.classList.remove('hidden');
            } finally {
                btns.forEach(b => b.disabled = rows.length === 0);
                open();
                loader.hide();
            }
        }

        /* ---------- downloads (built in the browser from the rows already loaded) ---------- */
        const loaded = {};
        function loadScript(src) {
            if (!loaded[src]) {
                loaded[src] = new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = src; s.onload = resolve;
                    s.onerror = () => { delete loaded[src]; reject(new Error('Could not load ' + src)); };
                    document.head.appendChild(s);
                });
            }
            return loaded[src];
        }

        async function download(type) {
            if (!rows.length) return;
            const header = ['#', 'Trip Sched', 'Status', 'Initial Name', 'Vessel Name', 'Remarks'];
            const data   = rows.map((r, i) => [i + 1, r.sched, r.status, r.initial, r.vessel, r.remarks]);
            const base   = `Cancelled-Schedules_${meta.month}-${meta.year}`;

            loader.show('Preparing file…');
            try {
                if (type === 'xlsx') {
                    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js');
                    const ws = XLSX.utils.aoa_to_sheet([header, ...data]);
                    ws['!cols'] = [{ wch: 5 }, { wch: 22 }, { wch: 12 }, { wch: 16 }, { wch: 24 }, { wch: 34 }];
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Cancelled Schedules');
                    XLSX.writeFile(wb, base + '.xlsx');
                } else {
                    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
                    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js');
                    const doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
                    doc.setFontSize(14);
                    doc.text('Cancelled Schedules', 40, 40);
                    doc.setFontSize(9);
                    doc.setTextColor(100);
                    doc.text(`${meta.month} ${meta.year}   |   Route: ${meta.route}   |   Vessel: ${meta.vessel}   |   Total: ${rows.length}`, 40, 56);
                    doc.autoTable({
                        head: [header], body: data, startY: 70, theme: 'grid',
                        styles: { fontSize: 8.5, cellPadding: 4 },
                        headStyles: { fillColor: [11, 31, 92], halign: 'center' },
                        columnStyles: { 0: { halign: 'center', cellWidth: 30 } },
                        margin: { left: 40, right: 40 },
                    });
                    doc.save(base + '.pdf');
                }
            } catch (e) {
                console.error('Download failed:', e);
                alert('Could not create the file. Please check your internet connection and try again.');
            } finally {
                loader.hide();
            }
        }

        window.openCancelled     = load;
        window.closeCancelled    = close;
        window.downloadCancelled = download;
        document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    })();
    </script>
</x-app-layout>