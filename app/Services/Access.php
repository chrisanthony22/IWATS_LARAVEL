<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * THE one place that answers "may this user do <action> in <module>?"
 * Used by the `module` route middleware, the @module Blade directive and the nav.
 *
 *   Access::can(auth()->user(), 'users', 'create')
 *
 * Actions: view, create, edit, delete.
 * Admin user types (ADMIN_USERTYPE_IDS in .env) always have full access and are not editable.
 * A user without a user type, or a module without a row, gets no access (deny by default).
 */
class Access
{
    /** @var array<int, array> access map per user type, kept for the current request */
    private static array $maps = [];

    public static function isAdmin(?Authenticatable $user): bool
    {
        return $user !== null
            && in_array((int) ($user->usertype_id ?? 0), config('iwats.admin_type_ids', []), true);
    }

    public static function can(?Authenticatable $user, string $module, string $action = 'view'): bool
    {
        if (! $user) {
            return false;
        }
        if (self::isAdmin($user)) {
            return true;
        }

        $typeId = (int) ($user->usertype_id ?? 0);
        if ($typeId === 0) {
            return false;
        }

        self::$maps[$typeId] ??= app(DataService::class)->getAccessMap($typeId);

        return (bool) (self::$maps[$typeId][$module][$action] ?? false);
    }
}