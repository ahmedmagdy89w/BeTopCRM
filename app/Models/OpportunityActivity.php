<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityActivity extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'opportunity_id', 'user_id', 'type', 'channel', 'subject',
        'description', 'outcome', 'due_date', 'reminder_at', 'reminder_sent_at', 'completed_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'reminder_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const TYPES = ['call', 'email', 'meeting', 'task', 'whatsapp', 'note'];

    const CHANNELS = ['call', 'email', 'meeting', 'whatsapp', 'in_person', 'other'];

    const OUTCOMES = ['positive', 'neutral', 'negative', 'escalated'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted() && $this->due_date !== null && $this->due_date->isPast();
    }

    public function isReminderDue(): bool
    {
        return ! $this->isCompleted()
            && $this->reminder_at !== null
            && $this->reminder_sent_at === null
            && $this->reminder_at->isPast();
    }

    public function markCompleted(?string $outcome = null): void
    {
        $this->update([
            'completed_at' => now(),
            'outcome' => $outcome,
        ]);
    }
}
