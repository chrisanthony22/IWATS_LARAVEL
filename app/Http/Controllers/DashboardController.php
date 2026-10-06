<?php

namespace App\Http\Controllers;

use App\Services\DataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /** Bump this number whenever the cached data gains or loses a field, so old cache entries are ignored. */
    private const CACHE_VERSION = 10;

    private const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    /** Reads the filters from the URL once, so the page and the modal always agree. */
    private function filters(Request $request, DataService $data): array
    {
        // an unknown/missing month falls back to the CURRENT Philippine month
        $monthIndex = array_search($request->input('month', now('Asia/Manila')->format('F')), self::MONTHS);

        $trips   = $data->getTripLocations()->pluck('ID', 'TripLocation');   // name => id  (cached)
        $vessels = $data->getVesselNames()->pluck('ID', 'VesselName');       // name => id  (cached)

        return [
            'trips'    => $trips,
            'vessels'  => $vessels,
            'month'    => $monthIndex === false ? now('Asia/Manila')->month : $monthIndex + 1,
            'year'     => (int) $request->input('year', now('Asia/Manila')->year),
            // "ALL" / unknown names are not in the lookup, so they become 0 = no filter
            'tripId'   => (int) ($trips[$request->input('route')] ?? 0),
            'vesselId' => (int) ($vessels[$request->input('vessel_name')] ?? 0),
            'channel'  => (string) $request->input('channel', 'All'),
        ];
    }

    /**
     * Each part of the dashboard is cached on its own, keyed ONLY by the filters it really uses.
     * So changing the Month no longer recalculates the Monthly Sales Graph, and changing the Channel
     * no longer recalculates the pie. Add ?refresh=1 to the URL to force fresh numbers.
     */
    private function cached(Request $request, string $name, array $keyParts, int $ttl, \Closure $build)
    {
        $key = 'dashboard:v' . self::CACHE_VERSION . ":{$name}:" . md5(json_encode($keyParts));

        if ($request->boolean('refresh')) {
            Cache::forget($key);
        }

        return Cache::remember($key, $ttl, $build);
    }

    /** Current month: 5 minutes (data still changes). Past months: 1 hour (data is settled). */
    private function ttl(array $f): int
    {
        $now = now('Asia/Manila');

        return ($f['year'] === $now->year && $f['month'] === $now->month) ? 300 : 3600;
    }

    public function index(Request $request, DataService $data)
    {
        set_time_limit(120);   // safety net while the database is slow; remove once it's fast

        $f   = $this->filters($request, $data);
        $ttl = $this->ttl($f);
        $own = [$f['tripId'], $f['vesselId'], $f['month'], $f['year']];

        // cards: depend on route, vessel, month, year and channel
        $cards = $this->cached($request, 'cards', [...$own, $f['channel']], $ttl, fn () => [
            'cancelledTrips' => $data->cancelledTripsCount($f['tripId'], $f['vesselId'], $f['month'], $f['year']),
            'passenger'      => $data->getPassengerSummary($f['tripId'], $f['vesselId'], $f['month'], $f['year'], $f['channel']),
            'vehicle'        => $data->getVehicleSummary($f['tripId'], $f['vesselId'], $f['month'], $f['year'], $f['channel']),
        ]);

        // pie: the Channel dropdown does not apply to it, so it is not part of its key
        $channelSales = $this->cached($request, 'pie', $own, $ttl, fn () =>
            $data->getSalesByChannel($f['tripId'], $f['vesselId'], $f['month'], $f['year']));

        $topTrips = $this->cached($request, 'top-trips', [...$own, $f['channel']], $ttl, fn () =>
            $data->getTopTrips($f['tripId'], $f['vesselId'], $f['month'], $f['year'], $f['channel']));

        // monthly graph: shows every month of every year, so Month and Year are NOT part of its key
        $forecast = $this->cached($request, 'monthly', [$f['tripId'], $f['vesselId'], $f['channel']], 300, fn () =>
            $data->getMonthlySales($f['tripId'], $f['vesselId'], $f['channel']));

        return view('dashboard', $cards + [
            'channelSales'   => $channelSales,
            'topTrips'       => $topTrips,
            'forecast'       => $forecast,
            'selectedMonth'  => self::MONTHS[$f['month'] - 1],
            'routeOptions'   => array_merge(['ALL'], $f['trips']->keys()->all()),
            'vesselOptions'  => array_merge(['All'], $f['vessels']->keys()->all()),
            'channelOptions' => array_merge(['All'], $data->getChannelOptions()),   // cached
        ]);
    }

    /** JSON for the "Cancelled Schedules" modal (same filters as the card). */
    public function cancelledTrips(Request $request, DataService $data)
    {
        set_time_limit(120);

        $f = $this->filters($request, $data);

        $rows = $this->cached($request, 'cancelled-list', [$f['tripId'], $f['vesselId'], $f['month'], $f['year']], $this->ttl($f), fn () =>
            $data->getCancelledTrips($f['tripId'], $f['vesselId'], $f['month'], $f['year'])
                ->map(fn ($r) => [
                    'sched'   => $r->TripSched,
                    'status'  => 'Cancelled',
                    'initial' => $r->InitialName,
                    'vessel'  => $r->VesselName,
                    'remarks' => $r->Remarks ?? '',
                ])->values()->all()
        );

        return response()->json($rows);
    }
}