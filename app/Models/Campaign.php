<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Auditable;

class Campaign extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name', 'email_template_id', 'status',
        'segment_filters', 'scheduled_at', 'created_by',
    ];

    protected $casts = [
        'segment_filters' => 'array',
        'scheduled_at'    => 'datetime',
        'sent_at'         => 'datetime',
    ];

    const STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'cancelled'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipientCount(): int
    {
        return $this->recipients()->count();
    }

    public function sentCount(): int
    {
        return $this->recipients()->where('status', 'sent')->count();
    }

    public function failedCount(): int
    {
        return $this->recipients()->where('status', 'failed')->count();
    }
}
