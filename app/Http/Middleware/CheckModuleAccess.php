<?php

namespace App\Http\Middleware;

use App\Services\Access;
use Closure;
use Illuminate\Http\Request;

/**
 * Usage on a route:  ->middleware('module:users,edit')
 * The action defaults to "view":  ->middleware('module:audit-logs')
 */
class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, string $module, string $action = 'view')
    {
        abort_unless(
            Access::can($request->user(), $module, $action),
            403,
            'You do not have access to this module.'
        );

        return $next($request);
    }
}