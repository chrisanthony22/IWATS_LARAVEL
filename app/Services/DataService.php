<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * CENTRAL DATA FILE: every database read/write used by the system lives here.
 * Controllers stay thin and just call these methods.
 */
class DataService
{
    /**
     * Channel groups: dropdown choice => AgentGroup.category values it covers.
     * null = rows with an empty category. Text keys must match your data exactly.
     */
    private const CHANNEL_GROUPS = [
        'Agent' => ['Agent', 'Complimentary', null],
    ];

    /** First year shown on the Monthly Sales Graph. The last year is always the current year. */
    private const MONTHLY_START_YEAR = 2023;

    private function channelCategories(string $channel): array
    {
        return self::CHANNEL_GROUPS[$channel] ?? [$channel];
    }

    /* =====================================================================
     |  SHARED HELPERS
     ===================================================================== */

    private function filters(array $f): array
    {
        return array_merge([
            'month' => now()->month, 'year' => now()->year,
            'route' => 'ALL', 'vessel_name' => 'All', 'channel' => 'All',
        ], $f);
    }

    private function days(array $f): int
    {
        $f = $this->filters($f);
        return Carbon::create($f['year'], $f['month'])->daysInMonth;
    }

    /* =====================================================================
     |  READ: DROPDOWN OPTIONS
     ===================================================================== */

    /**
     * Cache query rows as plain arrays. Laravel 12/13 refuses to restore cached objects
     * (you get __PHP_Incomplete_Class), so we store arrays and rebuild the rows on the way out.
     */
    private function rememberRows(string $key, int $ttl, \Closure $query): Collection
    {
        $rows = Cache::remember($key, $ttl, fn () => $query()->map(fn ($r) => (array) $r)->all());

        return collect($rows)->map(fn ($r) => (object) $r);
    }

    public function getTripLocations(): Collection
    {
        return $this->rememberRows('dd:trip_locations', 600, fn () =>
            DB::table('m_TripLocation')->where('IsActive', 1)->orderBy('id', 'asc')->get());
    }

    public function getVesselNames(): Collection
    {
        return $this->rememberRows('dd:vessels', 600, fn () =>
            DB::table('m_Vessel_HD')->orderBy('id', 'asc')->get());
    }

    /* =====================================================================
     |  READ: VOYAGES (same logic as dashboard_LoadVoyage, no stored procedure)
     ===================================================================== */

    /** Shared base query. Pass 0 for any filter you want to skip. */
    private function voyageQuery(int $tripId = 0, int $vesselId = 0, int $month = 0, int $year = 0): Builder
    {
        $q = DB::table('t_ScheduleList as SL')
            ->join('m_TripLocation as TL', 'SL.TripID', '=', 'TL.ID')
            ->join('m_Vessel_HD as VH', 'SL.VesselID', '=', 'VH.ID');
    
        if ($range = $this->periodRange($month, $year)) {
            $q->whereRaw('CAST(SL.ETD AS DATETIME) >= ? AND CAST(SL.ETD AS DATETIME) < ?', $range);
        } elseif ($month) {
            $q->whereRaw('MONTH(CAST(SL.ETD AS DATETIME)) = ?', [$month]);
        }
    
        return $q
            ->when($tripId,   fn ($q) => $q->where('SL.TripID', $tripId))
            ->when($vesselId, fn ($q) => $q->where('SL.VesselID', $vesselId));
    }

    /** The number for the Total Cancelled Trips card. */
    public function cancelledTripsCount(int $tripId = 0, int $vesselId = 0, int $month = 0, int $year = 0): int
    {
        return $this->voyageQuery($tripId, $vesselId, $month, $year)
            ->where('SL.IsCancel', 1)
            ->whereNotNull('SL.Remarks')
            ->where('SL.Remarks', '!=', '')
            ->select('SL.TripID', 'SL.VesselID', 'SL.ETD')
            ->distinct()
            ->get()
            ->count();
    }

    /** The cancelled voyages themselves (for the "See Details" modal). */
    public function getCancelledTrips(int $tripId = 0, int $vesselId = 0, int $month = 0, int $year = 0): Collection
    {
        return $this->voyageQuery($tripId, $vesselId, $month, $year)
            ->where('SL.IsCancel', 1)
            ->whereNotNull('SL.Remarks')
            ->where('SL.Remarks', '!=', '')
            ->selectRaw('CAST(SL.ETD AS DATETIME) as ETDate, TL.InitialName, TL.TripLocation, VH.VesselName, SL.Remarks, SL.IsRoRo')
            ->distinct()
            ->orderBy('ETDate')
            ->get()
            ->map(function ($row) {
                $row->TripSched = Carbon::parse($row->ETDate)->format('M d, Y h:i A');
                return $row;
            });
    }

    /* =====================================================================
     |  READ: PASSENGER / VEHICLE CARDS
     ===================================================================== */

    /** Same filters for passengers and vehicles. 0 / 'All' = no filter. Channel = AgentGroup.category */
    private function applyScheduleFilters(Builder $q, int $tripId, int $vesselId, int $month, int $year, string $channel): Builder
    {
        if ($range = $this->periodRange($month, $year)) {
            $q->whereRaw('CAST(SL.ETD AS DATETIME) >= ? AND CAST(SL.ETD AS DATETIME) < ?', $range);
        } elseif ($month) {
            $q->whereRaw('MONTH(CAST(SL.ETD AS DATETIME)) = ?', [$month]);
        }
    
        $q->when($tripId,   fn ($q) => $q->where('SL.TripID', $tripId))
        ->when($vesselId, fn ($q) => $q->where('SL.VesselID', $vesselId));
    
        if ($channel !== '' && strtolower($channel) !== 'all') {
            $cats = $this->channelCategories($channel);
            $q->where(function ($w) use ($cats) {
                $w->whereIn('AG.category', array_values(array_filter($cats, fn ($c) => $c !== null)));
                if (in_array(null, $cats, true)) {
                    $w->orWhereNull('AG.category');          // empty category counts too
                }
            });
        }
    
        return $q;
    }

    /** Builds the card array the dashboard expects. */
    private function summaryCard(string $label, string $dailyLabel, int $count, float $sales, int $voyages, int $month, int $year): array
    {
        $days = $month
            ? Carbon::create($year ?: now('Asia/Manila')->year, $month)->daysInMonth
            : ($year ? (Carbon::create($year)->isLeapYear() ? 366 : 365) : 1);

        return ['total' => $count, 'label' => $label, 'stats' => [
            'Total Sales'      => number_format($sales, 2),
            'Total Voyage'     => number_format($voyages),
            $dailyLabel        => number_format(floor($count / $days)),
            'Ave. Sales Daily' => number_format($sales / $days, 2),
        ]];
    }

    /** Passenger card: Total Pax, Total Sales, Total Voyage, daily averages. */
    public function getPassengerSummary(int $tripId = 0, int $vesselId = 0, int $month = 0, int $year = 0, string $channel = 'All'): array
    {
        $q = DB::table('t_PassengerTransaction as PT')
            ->join('t_ScheduleList as SL', 'PT.SchedID', '=', 'SL.ID')
            ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID')
            ->whereIn('PT.Status', [1, 4, 5, 21])
            ->whereNotIn('PT.SaveAgentID', [69, 99, 162, 1210]);

        $r = $this->applyScheduleFilters($q, $tripId, $vesselId, $month, $year, $channel)
            ->selectRaw('COALESCE(SUM(PT.GrossAmount), 0)
                         - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0)) AS TotalSales,
                         COUNT(PT.ID) AS PassCount,
                         COUNT(DISTINCT SL.ID) AS TotalTrips')
            ->first();

        return $this->summaryCard('Total Pax', 'Ave. Pax Daily', (int) $r->PassCount, (float) $r->TotalSales, (int) $r->TotalTrips, $month, $year);
    }

    /** Vehicle card: Total Cargo, Total Sales, Total Voyage, daily averages. */
    public function getVehicleSummary(int $tripId = 0, int $vesselId = 0, int $month = 0, int $year = 0, string $channel = 'All'): array
    {
        $q = DB::table('t_VehicleTransaction as PT')
            ->join('t_ScheduleList as SL', 'PT.SchedID', '=', 'SL.ID')
            ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID')
            ->whereIn('PT.Status', ['1', '4', '5', '21']);

        $r = $this->applyScheduleFilters($q, $tripId, $vesselId, $month, $year, $channel)
            ->selectRaw('COALESCE(SUM(PT.Amount), 0)
                         - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0)) AS TotalSales,
                         COUNT(PT.VTransNo) AS VCount,
                         COUNT(DISTINCT SL.ID) AS TotalTrips')
            ->first();

        return $this->summaryCard('Total Cargo', 'Ave. Cargo Daily', (int) $r->VCount, (float) $r->TotalSales, (int) $r->TotalTrips, $month, $year);
    }

    /* =====================================================================
     |  READ: SALES BY CHANNEL (pie chart)
     ===================================================================== */

    /** Maps a raw AgentGroup.category to the channel group shown in the chart (Complimentary / empty -> Agent). */
    private function channelGroupOf(?string $category): string
    {
        foreach (self::CHANNEL_GROUPS as $group => $members) {
            if (in_array($category, $members, true)) {
                return $group;
            }
        }
        return $category ?? 'Unassigned';
    }

    /**
     * Pie chart data: sales and pax count per channel group.
     * Sales = passenger sales + vehicle sales. Pax = passenger count. Cargo = vehicle count.
     * The Channel dropdown does not apply here (the pie shows all channels).
     */
    public function getSalesByChannel(int $tripId = 0, int $vesselId = 0, int $month = 0, int $year = 0): array
    {
        $pax = $this->applyScheduleFilters(
            DB::table('t_PassengerTransaction as PT')
                ->join('t_ScheduleList as SL', 'PT.SchedID', '=', 'SL.ID')
                ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID')
                ->whereIn('PT.Status', [1, 4, 5, 21])
                ->whereNotIn('PT.SaveAgentID', [69, 99, 162, 1210]),
            $tripId, $vesselId, $month, $year, 'All'
        )
            ->selectRaw("'pax' AS Kind, AG.category,
                        COALESCE(SUM(PT.GrossAmount), 0)
                        - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0)) AS TotalSales,
                        COUNT(PT.ID) AS Cnt")
            ->groupBy('AG.category');
    
        $veh = $this->applyScheduleFilters(
            DB::table('t_VehicleTransaction as PT')
                ->join('t_ScheduleList as SL', 'PT.SchedID', '=', 'SL.ID')
                ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID')
                ->whereIn('PT.Status', ['1', '4', '5', '21']),
            $tripId, $vesselId, $month, $year, 'All'
        )
            ->selectRaw("'cargo' AS Kind, AG.category,
                        COALESCE(SUM(PT.Amount), 0)
                        - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0)) AS TotalSales,
                        COUNT(PT.VTransNo) AS Cnt")
            ->groupBy('AG.category');
    
        $out = [];
        foreach ($pax->unionAll($veh)->get() as $r) {
            $g = $this->channelGroupOf($r->category);
            $out[$g] ??= ['sales' => 0.0, 'pax' => 0, 'cargo' => 0];
            $out[$g]['sales']     += (float) $r->TotalSales;
            $out[$g][$r->Kind]    += (int) $r->Cnt;               // Kind is 'pax' or 'cargo'
        }
    
        uasort($out, fn ($a, $b) => $b['sales'] <=> $a['sales']);   // biggest first
    
        return [
            'labels' => array_keys($out),
            'sales'  => array_map(fn ($v) => round($v['sales'], 2), array_values($out)),
            'pax'    => array_map(fn ($v) => (int) $v['pax'], array_values($out)),
            'cargo'  => array_map(fn ($v) => (int) $v['cargo'], array_values($out)),
        ];
    }

    private function periodRange(int $month, int $year): ?array
    {
        if ($year && $month) {
            $s = Carbon::create($year, $month, 1)->startOfDay();
            $e = $s->copy()->addMonth();
        } elseif ($year) {
            $s = Carbon::create($year, 1, 1)->startOfDay();
            $e = $s->copy()->addYear();
        } else {
            return null;
        }
        return [$s->format('Ymd') . ' 00:00:00', $e->format('Ymd') . ' 00:00:00'];   // 'Ymd' is language-neutral for SQL Server
    }

    /* =====================================================================
     |  READ: TOP TRIPS PER ROUTE (bar chart)  -  replaces dashboard_TopTrips
     ===================================================================== */

    /**
     * One bar per departure time + TripID for the selected period, ordered by time of day (earliest first).
     * Uniqueness is ETD (time of day) + TripID only. The vessel is NOT part of it: if different vessels ran
     * the same time + trip, they are merged into one bar (the tooltip lists every vessel).
     * EVERY scheduled departure is listed, even if nothing was sold on it (its bar is simply 0).
     * Sales = passenger sales + vehicle sales. Also returns pax count and cargo count, so the chart's
     * "Show Data By" select can switch between Sales / Pax Count / Cargo Count.
     * Month, Year, Route and Vessel decide which departures are listed; Channel only narrows the numbers.
     */
    public function getTopTrips(int $tripId = 0, int $vesselId = 0, int $month = 0, int $year = 0, string $channel = 'All'): array
    {
        $includeCancelled = false;      // true = also list departures that were cancelled (they always show 0)
    
        $cols = "SL.TripID, TL.InitialName, VH.VesselName, CAST(SL.ETD AS DATETIME) AS ETDate";
        $grp  = "SL.TripID, TL.InitialName, VH.VesselName, CAST(SL.ETD AS DATETIME)";
    
        // 1) every scheduled departure for the filters, whether or not anything was sold (channel does not apply here)
        $scheduled = $this->applyScheduleFilters(
            DB::table('t_ScheduleList as SL')
                ->join('m_TripLocation as TL', 'SL.TripID', '=', 'TL.ID')
                ->join('m_Vessel_HD as VH', 'SL.VesselID', '=', 'VH.ID')
                ->when(! $includeCancelled, fn ($q) => $q->where(fn ($w) => $w->where('SL.IsCancel', 0)->orWhereNull('SL.IsCancel'))),
            $tripId, $vesselId, $month, $year, 'All'
        )
            ->selectRaw($cols)
            ->distinct()
            ->get();
    
        // 2) sales / counts per departure: passengers + vehicles in one query
        $base = fn (string $txTable) => DB::table('t_ScheduleList as SL')
            ->join('m_TripLocation as TL', 'SL.TripID', '=', 'TL.ID')
            ->join('m_Vessel_HD as VH', 'SL.VesselID', '=', 'VH.ID')
            ->join("$txTable as PT", 'PT.SchedID', '=', 'SL.ID')
            ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID');
    
        $paxQ = $this->applyScheduleFilters(
            $base('t_PassengerTransaction')
                ->whereIn('PT.Status', [1, 4, 5, 21])
                ->whereNotIn('PT.SaveAgentID', [69, 99, 162, 1210]),
            $tripId, $vesselId, $month, $year, $channel
        )
            ->selectRaw("'pax' AS Kind, $cols,
                        COALESCE(SUM(PT.GrossAmount), 0)
                        - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0)) AS TotalSales,
                        COUNT(PT.ID) AS Cnt")
            ->groupByRaw($grp);
    
        $vehQ = $this->applyScheduleFilters(
            $base('t_VehicleTransaction')->whereIn('PT.Status', ['1', '4', '5', '21']),
            $tripId, $vesselId, $month, $year, $channel
        )
            ->selectRaw("'cargo' AS Kind, $cols,
                        COALESCE(SUM(PT.Amount), 0)
                        - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0)) AS TotalSales,
                        COUNT(PT.VTransNo) AS Cnt")
            ->groupByRaw($grp);
    
        $sold = $paxQ->unionAll($vehQ)->get();
    
        // 3) one bar per time + TripID: departures first (all start at 0), then add the numbers
        $groups = [];
        $add = function ($r, ?string $kind) use (&$groups) {
            $timeString = date('h:i A', strtotime($r->ETDate));      // e.g. '07:30 AM'
            $key = $timeString . '|' . $r->TripID;                   // same departure time + same TripID = same bar (vessel is NOT part of it)
            $g = $groups[$key] ?? ['label' => trim($timeString . ' ' . $r->InitialName), 'timeLabel' => $timeString,
                                'vessels' => [], 'sales' => 0.0, 'pax' => 0, 'cargo' => 0, 'etds' => [],
                                'time' => date('H:i:s', strtotime($r->ETDate))];   // 24h time, used for sorting
            $g['vessels'][$r->VesselName] = true;
            if ($kind) {                                             // null = just a scheduled departure, nothing sold
                $g['sales'] += (float) $r->TotalSales;
                $g[$kind]   += (int) $r->Cnt;
            }
            $g['etds'][$r->ETDate] = true;                           // distinct departures
            $groups[$key] = $g;
        };
        foreach ($scheduled as $r) $add($r, null);
        foreach ($sold      as $r) $add($r, $r->Kind);               // Kind is 'pax' or 'cargo'
    
        $g = array_values($groups);
        usort($g, fn ($a, $b) => [$a['time'], $a['label']] <=> [$b['time'], $b['label']]);   // time of day, ascending
    
        return [
            'labels'    => array_column($g, 'label'),
            'sales'     => array_map(fn ($x) => round($x['sales'], 2), $g),
            'pax'       => array_column($g, 'pax'),
            'cargo'     => array_column($g, 'cargo'),
            'schedules' => array_map(fn ($x) => count($x['etds']), $g),
            'vessels'   => array_map(fn ($x) => implode(', ', array_keys($x['vessels'])), $g),
            'times'     => array_column($g, 'timeLabel'),
        ];
    }

    /* =====================================================================
     |  READ: MONTHLY SALES GRAPH (line chart)
     ===================================================================== */

    /**
     * Net sales (passenger + vehicle) for each month, from MONTHLY_START_YEAR up to the current year, in millions.
     * e.g. ['2023' => [18.2, 10.1, ... 12 values], '2024' => [...], ...]. Months that have not happened yet are null.
     * The end year moves forward by itself every January. Route, Vessel and Channel filters apply;
     * the Month and Year dropdowns do not (the graph always shows every month of every year).
     */
    public function getMonthlySales(int $tripId = 0, int $vesselId = 0, string $channel = 'All'): array
    {
        $from = self::MONTHLY_START_YEAR;
        $to   = now('Asia/Manila')->year;
    
        $monthly = fn ($query, string $salesSql) => $this->applyScheduleFilters($query, $tripId, $vesselId, 0, 0, $channel)
            ->whereRaw('CAST(SL.ETD AS DATETIME) >= ? AND CAST(SL.ETD AS DATETIME) < ?', [$from . '0101 00:00:00', ($to + 1) . '0101 00:00:00'])
            ->selectRaw("YEAR(CAST(SL.ETD AS DATETIME)) AS Yr, MONTH(CAST(SL.ETD AS DATETIME)) AS Mn, $salesSql AS TotalSales")
            ->groupByRaw('YEAR(CAST(SL.ETD AS DATETIME)), MONTH(CAST(SL.ETD AS DATETIME))');
    
        $pax = $monthly(
            DB::table('t_PassengerTransaction as PT')
                ->join('t_ScheduleList as SL', 'PT.SchedID', '=', 'SL.ID')
                ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID')
                ->whereIn('PT.Status', [1, 4, 5, 21])
                ->whereNotIn('PT.SaveAgentID', [69, 99, 162, 1210]),
            'COALESCE(SUM(PT.GrossAmount), 0)
            - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0))'
        );
    
        $veh = $monthly(
            DB::table('t_VehicleTransaction as PT')
                ->join('t_ScheduleList as SL', 'PT.SchedID', '=', 'SL.ID')
                ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID')
                ->whereIn('PT.Status', ['1', '4', '5', '21']),
            'COALESCE(SUM(PT.Amount), 0)
            - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0))'
        );
    
        $totals = [];                                                // [year][month] => pax sales + vehicle sales
        foreach ($pax->unionAll($veh)->get() as $r) {
            $totals[(int) $r->Yr][(int) $r->Mn] = ($totals[(int) $r->Yr][(int) $r->Mn] ?? 0) + (float) $r->TotalSales;
        }
    
        $now = now('Asia/Manila');
        $out = [];
        for ($y = $from; $y <= $to; $y++) {
            $out[(string) $y] = array_map(
                fn ($m) => ($y === $now->year && $m > $now->month)
                    ? null                                           // future month: leave the line unfinished
                    : round(($totals[$y][$m] ?? 0) / 1_000_000, 2),
                range(1, 12)
            );
        }
        return $out;
    }

    /** Channel dropdown options, from the real AgentGroup.category values (merged categories are hidden). */
    public function getChannelOptions(): array
    {
        return Cache::remember('dd:channels', 600, function () {
            $merged = collect(self::CHANNEL_GROUPS)->flatten()->filter()      // ->filter() drops null
                ->diff(array_keys(self::CHANNEL_GROUPS))->values()->all();    // = ['Complimentary']

            return DB::table('AgentGroup')
                ->whereNotNull('category')
                ->whereNotIn('category', $merged)
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->all();
        });
    }


    /* =====================================================================
     |  PASTE INSIDE the DataService class.
     |  FIRST DELETE the previous AGENT_NAME_COLUMN constant, getAgentOptions(),
     |  applyAgentFilters() and getAgentSales(), then paste everything below.
     ===================================================================== */

    /** Column in the AgentGroup table that holds the agent's display name. */
    private const AGENT_NAME_COLUMN = 'AgentGroupName';

    /* =====================================================================
     |  READ: AGENT SALES DASHBOARD (date range, not month/year)
     ===================================================================== */

    /** Agent Name dropdown options (ALL is added by the controller). Only category "Agent" or empty. */
    public function getAgentOptions(): array
    {
        return Cache::remember('dd:agent_names:v2', 600, function () {
            $col = self::AGENT_NAME_COLUMN;
 
            return DB::table('AgentGroup')
                ->whereNotNull($col)
                ->where($col, '!=', '')
                ->where(fn ($w) => $w->where('category', 'Agent')->orWhereNull('category'))
                ->distinct()
                ->orderBy($col)
                ->pluck($col)
                ->all();
        });
    }

    /**
     * Date range (inclusive) + route + vessel + agent filters. 0 / 'ALL' = no filter.
     * Only AgentGroup rows whose category is "Agent" or empty (NULL) are included.
     */
    private function applyAgentFilters(Builder $q, int $tripId, int $vesselId, string $start, string $end, string $agentName): Builder
    {
        // 'Ymd' keeps the dates language-neutral for SQL Server
        $q->whereRaw('CAST(SL.ETD AS DATETIME) >= ?', [Carbon::parse($start)->format('Ymd') . ' 00:00:00'])
          ->whereRaw('CAST(SL.ETD AS DATETIME) < ?',  [Carbon::parse($end)->addDay()->format('Ymd') . ' 00:00:00'])
          ->when($tripId,   fn ($q) => $q->where('SL.TripID', $tripId))
          ->when($vesselId, fn ($q) => $q->where('SL.VesselID', $vesselId))
          ->where(fn ($w) => $w->where('AG.category', 'Agent')->orWhereNull('AG.category'));
 
        if ($agentName !== '' && strtoupper($agentName) !== 'ALL') {
            $q->where('AG.' . self::AGENT_NAME_COLUMN, $agentName);
        }
 
        return $q;
    }

    /**
     * Gross sales, net sales, pax count and cargo count per agent for a date range.
     * Same business rules as the main dashboard (passenger + vehicle, same statuses, same excluded agents).
     * Net = gross - (discount + amount claimed + agent commission).
     * EVERY agent with net sales above 0 is returned (no top-N limit here).
     * Returns:
     *   'agents' => [ ['id', 'name', 'gross', 'sales', 'pax', 'cargo'], ... ]   (biggest net sales first)
     *   'totals' => ['gross', 'sales', 'pax', 'cargo', 'agents']
     */
    public function getAgentSales(int $tripId = 0, int $vesselId = 0, string $start = '', string $end = '', string $agentName = 'ALL'): array
    {
        $nameCol = 'AG.' . self::AGENT_NAME_COLUMN;
        $cols    = "AG.ID AS AgentID, $nameCol AS AgentName";
        $grp     = "AG.ID, $nameCol";
 
        $pax = $this->applyAgentFilters(
            DB::table('t_PassengerTransaction as PT')
                ->join('t_ScheduleList as SL', 'PT.SchedID', '=', 'SL.ID')
                ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID')
                ->whereIn('PT.Status', [1, 4, 5, 21])
                ->whereNotIn('PT.SaveAgentID', [69, 99, 162, 1210]),
            $tripId, $vesselId, $start, $end, $agentName
        )
            ->selectRaw("'pax' AS Kind, $cols,
                         COALESCE(SUM(PT.GrossAmount), 0) AS Gross,
                         COALESCE(SUM(PT.GrossAmount), 0)
                         - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0)) AS TotalSales,
                         COUNT(PT.ID) AS Cnt")
            ->groupByRaw($grp);
 
        $veh = $this->applyAgentFilters(
            DB::table('t_VehicleTransaction as PT')
                ->join('t_ScheduleList as SL', 'PT.SchedID', '=', 'SL.ID')
                ->join('AgentGroup as AG', 'PT.SaveAgentID', '=', 'AG.ID')
                ->whereIn('PT.Status', ['1', '4', '5', '21']),
            $tripId, $vesselId, $start, $end, $agentName
        )
            ->selectRaw("'cargo' AS Kind, $cols,
                         COALESCE(SUM(PT.Amount), 0) AS Gross,
                         COALESCE(SUM(PT.Amount), 0)
                         - (COALESCE(SUM(PT.Discount), 0) + COALESCE(SUM(PT.AmountClaimed), 0) + COALESCE(SUM(PT.AgentComAmount), 0)) AS TotalSales,
                         COUNT(PT.VTransNo) AS Cnt")
            ->groupByRaw($grp);
 
        $rows = $pax->unionAll($veh)->get();      // one round trip instead of two
 
        // merge passenger + vehicle rows into one line per agent
        $agents = [];
        foreach ($rows as $r) {
            $id = (int) $r->AgentID;
            $agents[$id] ??= [
                'id'    => $id,
                'name'  => trim((string) ($r->AgentName ?? '')) ?: ('Agent #' . $id),
                'gross' => 0.0, 'sales' => 0.0, 'pax' => 0, 'cargo' => 0,
            ];
            $agents[$id]['gross']    += (float) $r->Gross;
            $agents[$id]['sales']    += (float) $r->TotalSales;
            $agents[$id][$r->Kind]   += (int) $r->Cnt;       // Kind is 'pax' or 'cargo'
        }
 
        foreach ($agents as &$a) {
            $a['gross'] = round($a['gross'], 2);
            $a['sales'] = round($a['sales'], 2);
        }
        unset($a);
 
        // keep only agents that actually have sales, biggest first
        $agents = array_values(array_filter($agents, fn ($a) => $a['sales'] > 0));
        usort($agents, fn ($a, $b) => $b['sales'] <=> $a['sales']);
 
        return [
            'agents' => $agents,
            'totals' => [
                'gross'  => round(array_sum(array_column($agents, 'gross')), 2),
                'sales'  => round(array_sum(array_column($agents, 'sales')), 2),
                'pax'    => array_sum(array_column($agents, 'pax')),
                'cargo'  => array_sum(array_column($agents, 'cargo')),
                'agents' => count($agents),
            ],
        ];
    }



    /** Filters -> query. Dates are inclusive. Search looks in description, user, IP, table and record id. */
    private function auditQuery(array $f): Builder
    {
        $q = DB::table('audit_logs')
            ->where('created_at', '>=', Carbon::parse($f['start'])->format('Ymd') . ' 00:00:00')
            ->where('created_at', '<',  Carbon::parse($f['end'])->addDay()->format('Ymd') . ' 00:00:00')   // 'Ymd' = language-neutral for SQL Server
            ->when($f['event'] !== '', fn ($q) => $q->where('event', $f['event']))
            ->when($f['table'] !== '', fn ($q) => $q->where('auditable_type', $f['table']))
            ->when($f['user'] > 0,     fn ($q) => $q->where('user_id', $f['user']));
    
        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';
            $q->where(fn ($w) => $w->where('description', 'like', $like)
                ->orWhere('user_name', 'like', $like)
                ->orWhere('ip_address', 'like', $like)
                ->orWhere('auditable_type', 'like', $like)
                ->orWhere('auditable_id', 'like', $like));
        }
    
        return $q;
    }
    
    /** One page of log rows, newest first. */
    public function getAuditLogs(array $f, int $perPage = 25): LengthAwarePaginator
    {
        return $this->auditQuery($f)->orderByDesc('id')->paginate($perPage);
    }
    
    /** Every matching row, in chunks (for the CSV export). $each receives one row object at a time. */
    public function streamAuditLogs(array $f, \Closure $each): void
    {
        $this->auditQuery($f)->orderBy('id')->chunkById(1000, function ($rows) use ($each) {
            foreach ($rows as $r) {
                $each($r);
            }
        }, 'id');
    }
    
    /** Overview numbers for the date range only (the other filters do not change them). */
    public function getAuditStats(array $f): array
    {
        $r = $this->auditQuery(array_merge($f, ['event' => '', 'table' => '', 'user' => 0, 'q' => '']))
            ->selectRaw("COUNT(*) AS total,
                        SUM(CASE WHEN event = 'login' THEN 1 ELSE 0 END) AS logins,
                        SUM(CASE WHEN event IN ('login_failed', 'lockout') THEN 1 ELSE 0 END) AS failed,
                        SUM(CASE WHEN event IN ('created', 'updated', 'deleted', 'restored') THEN 1 ELSE 0 END) AS changes")
            ->first();
    
        return ['total' => (int) $r->total, 'logins' => (int) $r->logins, 'failed' => (int) $r->failed, 'changes' => (int) $r->changes];
    }
    
    /** Dropdown options for the filters. Plain arrays, cached for 2 minutes. */
    public function getAuditOptions(): array
    {
        return Cache::remember('dd:audit_options', 120, fn () => [
            'events' => DB::table('audit_logs')->distinct()->orderBy('event')->pluck('event')->all(),
            'tables' => DB::table('audit_logs')->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type')->all(),
            'users'  => DB::table('audit_logs')->whereNotNull('user_id')
                ->selectRaw('user_id, MAX(user_name) AS user_name')->groupBy('user_id')->orderBy('user_name')->get()
                ->map(fn ($u) => ['id' => (int) $u->user_id, 'name' => $u->user_name ?: ('User #' . $u->user_id)])->all(),
        ]);
    }


    /** FOR USER ACCOUNT CRUD FUNCTIONS */
    /** User Type dropdown options (ID, UserType). */
    public function getUserTypes(): Collection
    {
        return $this->rememberRows('dd:user_types', 300, fn () =>
            DB::table('IWMS_UserTypes')->select('ID', 'UserType')->orderBy('UserType')->get());
    }
 
    /** One page of users with their user type name. $q searches name + email. $typeId 0 = all types. */
    public function getUserAccounts(string $q = '', int $typeId = 0, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->leftJoin('IWMS_UserTypes as UT', 'users.usertype_id', '=', 'UT.ID')
            ->select('users.id', 'users.name', 'users.email', 'users.usertype_id', 'users.created_at', 'UT.UserType as usertype_name')
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . $q . '%';
                $w->where(fn ($x) => $x->where('users.name', 'like', $like)->orWhere('users.email', 'like', $like));
            })
            ->when($typeId > 0, fn ($w) => $w->where('users.usertype_id', $typeId))
            ->orderBy('users.name')
            ->orderBy('users.id')              // SQL Server paging needs a stable ORDER BY
            ->paginate($perPage);
    }
 
    /**
     * Creates the user with a random password and calls $notify($user, $plainPassword) to email it.
     * The plain password only exists inside this method and the $notify closure: it is never returned,
     * flashed, logged or shown. If $notify throws (mail failed), the insert is rolled back.
     */
    public function createUserAccount(array $d, \Closure $notify): User
    {
        return DB::transaction(function () use ($d, $notify) {
            $plain = Str::password(12, symbols: false);
 
            $user = new User();
            $user->forceFill([
                'name'              => $d['name'],
                'email'             => $d['email'],
                'usertype_id'       => (int) $d['usertype_id'],
                'password'          => Hash::make($plain),
                'email_verified_at' => now(),            // created by an admin, so no verification step
            ])->save();
 
            $notify($user, $plain);
 
            return $user;
        });
    }
 
    public function updateUserAccount(User $user, array $d): User
    {
        $user->forceFill([
            'name'        => $d['name'],
            'email'       => $d['email'],
            'usertype_id' => (int) $d['usertype_id'],
        ])->save();
 
        return $user;
    }
 
    /** Sets a new random password and emails it (same rules as createUserAccount). */
    public function resetUserPassword(User $user, \Closure $notify): void
    {
        DB::transaction(function () use ($user, $notify) {
            $plain = Str::password(12, symbols: false);
 
            $user->forceFill([
                'password'       => Hash::make($plain),
                'remember_token' => null,                 // signs out any "remember me" sessions
            ])->save();
 
            $notify($user, $plain);
        });
    }
 
    public function deleteUserAccount(User $user): void
    {
        $user->delete();
    }
    /** END OF USER ACCOUNT CRUD FUNCTIONS */


    /* =====================================================================
     |  READ / WRITE: MODULE ACCESS (role based restrictions)
     ===================================================================== */
 
    /** Every module (screen) of the system, in display order. */
    public function getModules(): Collection
    {
        return $this->rememberRows('dd:modules', 300, fn () =>
            DB::table('IWMS_Modules')->select('ID', 'ModuleKey', 'ModuleName', 'SortOrder')
                ->orderBy('SortOrder')->orderBy('ModuleName')->get());
    }
 
    /** Flags for the edit screen: [moduleId => ['view' => bool, 'create' => bool, 'edit' => bool, 'delete' => bool]]. Not cached. */
    public function getAccessByModuleId(int $typeId): array
    {
        return DB::table('IWMS_UserTypeAccess')
            ->where('UserTypeID', $typeId)
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->ModuleID => [
                'view'   => (bool) $r->CanView,
                'create' => (bool) $r->CanCreate,
                'edit'   => (bool) $r->CanEdit,
                'delete' => (bool) $r->CanDelete,
            ]])
            ->all();
    }
 
    /** Flags used by the middleware, directive and nav: [moduleKey => flags]. Cached per user type for 5 minutes. */
    public function getAccessMap(int $typeId): array
    {
        return Cache::remember("access:type:{$typeId}", 300, fn () =>
            DB::table('IWMS_UserTypeAccess as A')
                ->join('IWMS_Modules as M', 'A.ModuleID', '=', 'M.ID')
                ->where('A.UserTypeID', $typeId)
                ->get(['M.ModuleKey', 'A.CanView', 'A.CanCreate', 'A.CanEdit', 'A.CanDelete'])
                ->mapWithKeys(fn ($r) => [$r->ModuleKey => [
                    'view'   => (bool) $r->CanView,
                    'create' => (bool) $r->CanCreate,
                    'edit'   => (bool) $r->CanEdit,
                    'delete' => (bool) $r->CanDelete,
                ]])
                ->all()
        );
    }
 
    /**
     * Replaces everything one user type may do. $rows = [moduleId => ['view' => bool, 'create' => bool, 'edit' => bool, 'delete' => bool]].
     * Modules with nothing ticked get no row at all (= no access). The cache is cleared so it applies immediately.
     */
    public function saveAccess(int $typeId, array $rows): void
    {
        DB::transaction(function () use ($typeId, $rows) {
            DB::table('IWMS_UserTypeAccess')->where('UserTypeID', $typeId)->delete();
 
            $insert = [];
            foreach ($rows as $moduleId => $f) {
                if ($f['view'] || $f['create'] || $f['edit'] || $f['delete']) {
                    $insert[] = [
                        'UserTypeID' => $typeId,
                        'ModuleID'   => (int) $moduleId,
                        'CanView'    => (int) $f['view'],
                        'CanCreate'  => (int) $f['create'],
                        'CanEdit'    => (int) $f['edit'],
                        'CanDelete'  => (int) $f['delete'],
                    ];
                }
            }
            if ($insert) {
                DB::table('IWMS_UserTypeAccess')->insert($insert);
            }
        });
 
        Cache::forget("access:type:{$typeId}");
    }


    
}