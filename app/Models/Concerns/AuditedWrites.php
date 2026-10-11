<?php

namespace App\Services\Concerns;

use App\Services\Audit;
use Illuminate\Support\Facades\DB;

/**
 * For writes done with the query builder (your DataService style). Each helper does the write AND logs it,
 * inside one transaction, so the log can never say something happened that didn't.
 *
 * In DataService:   use App\Services\Concerns\AuditedWrites;   then inside the class:   use AuditedWrites;
 */
trait AuditedWrites
{
    /** INSERT a row and log it. Returns the new id. */
    protected function auditedInsert(string $table, array $data, string $keyColumn = 'id', ?string $description = null): int|string
    {
        return DB::transaction(function () use ($table, $data, $keyColumn, $description) {
            $id = DB::table($table)->insertGetId($data, $keyColumn);
            Audit::log('created', $description ?? "Created {$table} #{$id}", [$table, $id], null, $data);

            return $id;
        });
    }

    /** UPDATE one row by key and log ONLY the fields that really changed (before and after). */
    protected function auditedUpdate(string $table, string $keyColumn, int|string $id, array $data, ?string $description = null): bool
    {
        return DB::transaction(function () use ($table, $keyColumn, $id, $data, $description) {
            $before = DB::table($table)->where($keyColumn, $id)->first();
            if (! $before) {
                return false;
            }

            $before = (array) $before;
            $old = $new = [];
            foreach ($data as $column => $value) {
                if (! array_key_exists($column, $before) || (string) $before[$column] !== (string) $value) {
                    $old[$column] = $before[$column] ?? null;
                    $new[$column] = $value;
                }
            }
            if (! $new) {
                return true;                              // nothing changed: nothing to write, nothing to log
            }

            DB::table($table)->where($keyColumn, $id)->update($new);
            Audit::log('updated', $description ?? "Updated {$table} #{$id}", [$table, $id], $old, $new);

            return true;
        });
    }

    /** DELETE one row by key and log what it contained. */
    protected function auditedDelete(string $table, string $keyColumn, int|string $id, ?string $description = null): bool
    {
        return DB::transaction(function () use ($table, $keyColumn, $id, $description) {
            $before = DB::table($table)->where($keyColumn, $id)->first();
            if (! $before) {
                return false;
            }

            DB::table($table)->where($keyColumn, $id)->delete();
            Audit::log('deleted', $description ?? "Deleted {$table} #{$id}", [$table, $id], (array) $before);

            return true;
        });
    }
}