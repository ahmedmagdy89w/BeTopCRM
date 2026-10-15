<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'auditable_type', 'auditable_id',
        'action', 'old_values', 'new_values',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    private static array $hiddenFields = ['password', 'remember_token', 'two_factor_secret'];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Model $model, string $action): self
    {
        $oldValues = null;
        $newValues = null;

        if ($action !== 'created') {
            $old = $model->getOriginal();
            $oldValues = array_diff_key($old, array_flip(self::$hiddenFields));
        }

        if ($action !== 'deleted') {
            $new = $model->getAttributes();
            $newValues = array_diff_key($new, array_flip(self::$hiddenFields));
        }

        return static::create([
            'user_id'        => auth()->id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id'   => $model->getKey(),
            'action'         => $action,
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'created_at'     => now(),
        ]);
    }

    public function scopeForEntity(Builder $q, string $type, int $id): Builder
    {
        return $q->where('auditable_type', $type)->where('auditable_id', $id);
    }

    public function scopeByUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeAction(Builder $q, string $action): Builder
    {
        return $q->where('action', $action);
    }

    public function scopeDateRange(Builder $q, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $q->where('created_at', '>=', $from);
        }
        if ($to) {
            $q->where('created_at', '<=', $to . ' 23:59:59');
        }
        return $q;
    }
}
