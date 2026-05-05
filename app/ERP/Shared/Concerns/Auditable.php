<?php

namespace App\ERP\Shared\Concerns;

use App\ERP\Shared\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            static::writeAudit($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            static::writeAudit($model, 'updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model): void {
            static::writeAudit($model, 'deleted', $model->getOriginal(), null);
        });
    }

    protected static function writeAudit(Model $model, string $action, ?array $before, ?array $after): void
    {
        AuditTrail::query()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => (string) $model->getKey(),
            'before' => $before,
            'after' => $after,
            'metadata' => [
                'ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ],
        ]);
    }
}
