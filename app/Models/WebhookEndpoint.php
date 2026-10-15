<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use HasFactory;

    const SUPPORTED_EVENTS = [
        'lead.created', 'lead.updated', 'lead.status_changed',
        'lead.assigned', 'lead.deleted',
        'customer.created', 'customer.updated',
        'customer.deleted', 'customer.lead_status_changed',
        'customer.assigned', 'customer.converted',
        'opportunity.created', 'opportunity.updated',
        'opportunity.deleted', 'opportunity.stage_changed',
        'organization.created', 'organization.updated',
        'contact.created',
        'task.completed',
        'campaign.sent',
    ];

    protected $fillable = [
        'url', 'secret', 'events', 'is_active',
        'description', 'created_by',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    public function scopeSubscribedTo(Builder $q, string $event): void
    {
        $q->whereJsonContains('events', $event);
    }
}
