<?php

namespace App\Models\Concerns;

use App\Services\Audit;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * Add `use Auditable;` to ANY Eloquent model and every create / update / delete on it is logged automatically
 * (only the fields that changed are stored for updates).
 *
 * Optional on the model:  protected array $auditExclude = ['some_noisy_column'];
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            Audit::log('created', self::auditText('created', $model), $model, null, $model->auditAttributes($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->auditAttributes($model->getChanges());
            if (! $changes) {
                return;                                   // only updated_at changed: nothing worth logging
            }
            Audit::log('updated', self::auditText('updated', $model), $model,
                Arr::only($model->getOriginal(), array_keys($changes)), $changes);
        });

        static::deleted(function ($model) {
            Audit::log('deleted', self::auditText('deleted', $model), $model, $model->auditAttributes($model->getAttributes()));
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn ($model) => Audit::log('restored', self::auditText('restored', $model), $model));
        }
    }

    /** Attributes minus timestamps and anything the model excludes. */
    public function auditAttributes(array $attributes): array
    {
        return Arr::except($attributes, array_merge(['created_at', 'updated_at'], $this->auditExclude ?? []));
    }

    private static function auditText(string $event, $model): string
    {
        return ucfirst($event) . ' ' . class_basename($model) . ' #' . $model->getKey();
    }
}