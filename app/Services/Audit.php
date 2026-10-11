<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * THE one place that writes to the audit log. Use it from anywhere:
 *
 *   Audit::log('exported', 'Exported cancelled schedules as PDF');
 *   Audit::log('updated', 'Changed booking status', ['bookings', 15], ['status' => 1], ['status' => 5]);
 *   Audit::log('deleted', null, $someEloquentModel, $someEloquentModel->getAttributes());
 *
 * It never throws: if writing the log fails, the real action still goes through (the error is reported).
 */
class Audit
{
    /** Never stored (matched case-insensitively, at any depth). Only the fact that it changed is kept. */
    private const HIDDEN = ['password', 'password_confirmation', 'current_password', 'new_password',
                            'remember_token', '_token', 'token', 'api_token', 'secret', 'api_key', 'authorization'];

    /**
     * @param string                          $event       any word you like: login, logout, created, updated, deleted, exported, ...
     * @param Model|array|string|null         $subject     an Eloquent model, [tableName, id], or just a table name
     * @param Authenticatable|null            $actor       who did it (defaults to the logged-in user)
     */
    public static function log(
        string $event,
        ?string $description = null,
        Model|array|string|null $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?Authenticatable $actor = null,
    ): void {
        try {
            [$type, $id] = self::subjectOf($subject);
            $user    = $actor ?? auth()->user();
            $request = app()->runningInConsole() ? null : request();

            AuditLog::create([
                'user_id'        => $user?->getAuthIdentifier(),
                'user_name'      => $user ? mb_substr((string) ($user->name ?? $user->email ?? $user->getAuthIdentifier()), 0, 255) : null,
                'event'          => mb_substr($event, 0, 50),
                'auditable_type' => $type,
                'auditable_id'   => $id,
                'description'    => $description !== null ? mb_substr($description, 0, 500) : null,
                'old_values'     => self::clean($old),
                'new_values'     => self::clean($new),
                'url'            => $request ? mb_substr($request->fullUrl(), 0, 1000) : null,
                'method'         => $request?->method(),
                'ip_address'     => $request?->ip(),
                'user_agent'     => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'created_at'     => now('Asia/Manila'),
            ]);
        } catch (\Throwable $e) {
            report($e);        // never break the user's action because the log failed
        }
    }

    /** [table name, id] for a model, an [table, id] pair, or a bare table name. */
    private static function subjectOf(Model|array|string|null $subject): array
    {
        if ($subject instanceof Model) {
            return [$subject->getTable(), (string) $subject->getKey()];
        }
        if (is_array($subject)) {
            return [isset($subject[0]) ? (string) $subject[0] : null, isset($subject[1]) ? (string) $subject[1] : null];
        }
        return [$subject, null];
    }

    /** Removes secrets, shortens huge values, returns null when empty. */
    private static function clean(?array $data): ?array
    {
        if (! $data) {
            return null;
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::HIDDEN, true)) {
                $out[$key] = '[hidden]';
            } elseif (is_array($value)) {
                $out[$key] = self::clean($value);
            } elseif (is_string($value) && mb_strlen($value) > 1000) {
                $out[$key] = mb_substr($value, 0, 1000) . '…';
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}