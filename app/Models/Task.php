<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Traits\Auditable;

class Task extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'user_id', 'taskable_type', 'taskable_id',
        'title', 'description', 'priority', 'status',
        'due_date', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'due_date'     => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'priority' => 'medium',
        'status'   => 'pending',
    ];

    const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    const STATUSES   = ['pending', 'in_progress', 'completed', 'cancelled'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopePriority(Builder $q, string $priority): Builder
    {
        return $q->where('priority', $priority);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->where('due_date', '<', now())
                 ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeDueToday(Builder $q): Builder
    {
        return $q->whereDate('due_date', today());
    }

    public function scopeUpcoming(Builder $q, int $days = 7): Builder
    {
        return $q->whereBetween('due_date', [now(), now()->addDays($days)])
                 ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && !in_array($this->status, ['completed', 'cancelled']);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }
}
