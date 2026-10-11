<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row = one thing that happened. Rows can be added and read, never edited or deleted through the model.
 */
class AuditLog extends Model
{
    public $timestamps = false;                // we set created_at ourselves

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);      // returning false cancels the update
        static::deleting(fn () => false);      // ...and the delete (use a scheduled prune query if you ever need retention)
    }
}