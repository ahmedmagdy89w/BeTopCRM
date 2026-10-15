<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCustomFields;
use App\Models\Traits\HasNotes;
use App\Models\Traits\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Opportunity extends Model
{
    use Auditable, HasAttachments, HasCustomFields, HasFactory, HasNotes, HasTags;

    protected $fillable = [
        'customer_id', 'title', 'type', 'stage', 'amount', 'currency',
        'probability', 'expected_close_date', 'start_date', 'end_date', 'closed_at',
        'assigned_to', 'created_by', 'notes', 'custom_fields',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_close_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'initial',
        'stage' => 'prospecting',
        'currency' => 'USD',
    ];

    const STAGES = ['prospecting', 'qualification', 'proposal', 'negotiation', 'won', 'lost'];

    const TYPES = ['initial', 'new', 'upsell', 'cross_sell', 'renewal', 'downgrade'];

    const STAGE_TRANSITIONS = [
        'prospecting' => ['qualification', 'lost'],
        'qualification' => ['prospecting', 'proposal', 'lost'],
        'proposal' => ['qualification', 'negotiation', 'lost'],
        'negotiation' => ['proposal', 'won', 'lost'],
        'won' => [],
        'lost' => ['prospecting'],
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OpportunityActivity::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'opportunity_products')
            ->withPivot('id', 'quantity', 'unit_price', 'discount_percent', 'discount_amount', 'notes')
            ->withTimestamps()
            ->orderByPivot('id');
    }

    public function canTransitionTo(string $newStage): bool
    {
        return in_array($newStage, self::STAGE_TRANSITIONS[$this->stage] ?? []);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->stage, ['won', 'lost']);
    }

    public function scopeStage(Builder $query, string $stage): Builder
    {
        return $query->where('stage', $stage);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', ['won', 'lost']);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
