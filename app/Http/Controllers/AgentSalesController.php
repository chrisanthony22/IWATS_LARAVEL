<?php

namespace App\Http\Controllers;

use App\Services\DataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AgentSalesController extends Controller
{
    /** Bump this number whenever the cached data gains or loses a field, so old cache entries are ignored. */
    private const CACHE_VERSION = 2;

    /** Reads the filters from the URL. Dates default to the current Philippine month. */
    private function filters(Request $request, DataService $data): array
    {
        $now = now('Asia/Manila');

        $parse = function ($value, Carbon $fallback): Carbon {
            try {
                return $value ? Carbon::parse($value, 'Asia/Manila') : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };

        $start = $parse($request->input('start_date'), $now->copy()->startOfMonth());
        $end   = $parse($request->input('end_date'),   $now->copy()->endOfMonth());
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $trips   = $data->getTripLocations()->pluck('ID', 'TripLocation');   // name => id  (cached)
        $vessels = $data->getVesselNames()->pluck('ID', 'VesselName');       // name => id  (cached)

        return [
            'trips'     => $trips,
            'vessels'   => $vessels,
            'start'     => $start->format('Y-m-d'),
            'end'       => $end->format('Y-m-d'),
            // "ALL" / unknown names are not in the lookup, so they become 0 = no filter
            'tripId'    => (int) ($trips[$request->input('route')] ?? 0),
            'vesselId'  => (int) ($vessels[$request->input('vessel_name')] ?? 0),
            'agentName' => (string) $request->input('agent_name', 'ALL'),
        ];
    }

    /** Ranges that include today: cached 5 minutes. Fully past ranges: 1 hour. Add ?refresh=1 to force fresh numbers. */
    private function cached(Request $request, array $f, string $name, \Closure $build)
    {
        $key = 'agent-sales:v' . self::CACHE_VERSION . ":{$name}:" . md5(json_encode([$f['tripId'], $f['vesselId'], $f['start'], $f['end'], $f['agentName']]));

        if ($request->boolean('refresh')) {
            Cache::forget($key);
        }

        $ttl = $f['end'] >= now('Asia/Manila')->format('Y-m-d') ? 300 : 3600;

        return Cache::remember($key, $ttl, $build);
    }

    public function index(Request $request, DataService $data)
    {
        set_time_limit(120);

        $f = $this->filters($request, $data);

        $result = $this->cached($request, $f, 'agents', fn () =>
            $data->getAgentSales($f['tripId'], $f['vesselId'], $f['start'], $f['end'], $f['agentName'])
        );

        return view('agent-sales.dashboard', [
            'agents'        => $result['agents'],
            'totals'        => $result['totals'],
            'startDate'     => $f['start'],
            'endDate'       => $f['end'],
            'routeOptions'  => array_merge(['ALL'], $f['trips']->keys()->all()),
            'vesselOptions' => array_merge(['ALL'], $f['vessels']->keys()->all()),
            'agentOptions'  => array_merge(['ALL'], $data->getAgentOptions()),
        ]);
    }
}