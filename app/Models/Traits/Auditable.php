<?php

namespace App\Models\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLog::record($model, 'created');
        });

        static::updated(function (Model $model) {
            if ($model->wasChanged()) {
                AuditLog::record($model, 'updated');
            }
        });

        static::deleted(function (Model $model) {
            AuditLog::record($model, 'deleted');
        });
    }
}
