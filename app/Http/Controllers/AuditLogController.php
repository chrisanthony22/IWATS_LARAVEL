<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\DataService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** Filters from the URL. Dates default to the last 7 days (Philippine time). */
    private function filters(Request $request): array
    {
        $now = now('Asia/Manila');

        $parse = function ($value, Carbon $fallback): Carbon {
            try {
                return $value ? Carbon::parse($value, 'Asia/Manila') : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };

        $start = $parse($request->input('start_date'), $now->copy()->subDays(6));
        $end   = $parse($request->input('end_date'),   $now->copy());
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
            'event' => trim((string) $request->input('event', '')),
            'table' => trim((string) $request->input('table', '')),
            'user'  => (int) $request->input('user', 0),
            'q'     => trim((string) $request->input('q', '')),
        ];
    }

    /** Colour of the event pill (works on both themes). */
    private function badge(string $event): string
    {
        return match (true) {
            $event === 'login'                                => 'bg-emerald-500/15 text-emerald-500 border-emerald-500/30',
            in_array($event, ['login_failed', 'lockout'], true) => 'bg-red-500/15 text-red-500 border-red-500/30',
            $event === 'created'                              => 'bg-sky-500/15 text-sky-500 border-sky-500/30',
            $event === 'updated'                              => 'bg-amber-500/15 text-amber-500 border-amber-500/30',
            $event === 'deleted'                              => 'bg-rose-500/15 text-rose-500 border-rose-500/30',
            $event === 'exported'                             => 'bg-violet-500/15 text-violet-500 border-violet-500/30',
            in_array($event, ['password_changed', 'password_reset'], true) => 'bg-amber-500/15 text-amber-500 border-amber-500/30',
            default                                           => 'bg-slate-400/15 t-muted border-slate-400/30',   // logout, restored, anything custom
        };
    }

    public function index(Request $request, DataService $data)
    {
        set_time_limit(120);

        $f       = $this->filters($request);
        $perPage = in_array((int) $request->input('per_page'), [25, 50, 100], true) ? (int) $request->input('per_page') : 25;
        $logs    = $data->getAuditLogs($f, $perPage)->withQueryString();

        // shape each row for the table and for the details popup
        $rows = collect($logs->items())->map(fn ($r) => [
            'id'          => (int) $r->id,
            'time'        => Carbon::parse($r->created_at)->format('M d, Y h:i:s A'),
            'user'        => $r->user_name ?: 'System / Guest',
            'event'       => $r->event,
            'event_label' => ucfirst(str_replace('_', ' ', $r->event)),
            'badge'       => $this->badge($r->event),
            'table'       => $r->auditable_type,
            'record'      => $r->auditable_id,
            'description' => $r->description,
            'ip'          => $r->ip_address,
            'method'      => $r->method,
            'url'         => $r->url,
            'agent'       => $r->user_agent,
            'old'         => $r->old_values ? json_decode($r->old_values, true) : null,
            'new'         => $r->new_values ? json_decode($r->new_values, true) : null,
        ])->all();

        return view('logs.audit', [
            'logs'    => $logs,
            'rows'    => $rows,
            'stats'   => $data->getAuditStats($f),
            'options' => $data->getAuditOptions(),
            'f'       => $f,
            'perPage' => $perPage,
        ]);
    }

    /** Downloads every row that matches the current filters as a CSV (opens in Excel). */
    public function export(Request $request, DataService $data)
    {
        set_time_limit(300);

        $f    = $this->filters($request);
        $name = "audit-logs_{$f['start']}_to_{$f['end']}.csv";

        Audit::log('exported', "Exported audit logs to CSV ({$f['start']} to {$f['end']})");   // exporting the log is itself logged

        return response()->streamDownload(function () use ($data, $f) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");                                    // UTF-8 BOM so Excel reads it correctly
            fputcsv($out, ['ID', 'Date/Time', 'User', 'Event', 'Module', 'Record', 'Description', 'IP', 'Method', 'URL', 'Old values', 'New values']);

            $data->streamAuditLogs($f, function ($r) use ($out) {
                fputcsv($out, [
                    $r->id, $r->created_at, $r->user_name, $r->event, $r->auditable_type, $r->auditable_id,
                    $r->description, $r->ip_address, $r->method, $r->url, $r->old_values, $r->new_values,
                ]);
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}