<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCustomFields;
use App\Models\Traits\HasNotes;
use App\Models\Traits\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, HasAttachments, HasCustomFields, HasFactory, HasNotes, HasTags, SoftDeletes;

    protected $fillable = [
        'type', 'is_lead', 'lead_status',
        'assigned_to', 'created_by', 'account_manager_id',
        'source', 'estimated_value', 'expected_close_date', 'description',
        'first_name', 'last_name', 'email', 'phone', 'job_title', 'birthday', 'gender',
        'company_name', 'industry', 'website', 'tax_id',
        'contact_name', 'contact_email', 'contact_phone',
        'status', 'contract_value',
        'health_score', 'last_contacted_at', 'next_follow_up_at',
        'notes', 'converted_at', 'custom_fields',
        'contacts', 'addresses',
        'score', 'score_breakdown', 'scored_at',
    ];

    protected $casts = [
        'is_lead' => 'boolean',
        'estimated_value' => 'decimal:2',
        'expected_close_date' => 'date',
        'birthday' => 'date',
        'contract_value' => 'decimal:2',
        'health_score' => 'integer',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'converted_at' => 'datetime',
        'contacts' => 'array',
        'addresses' => 'array',
        'score' => 'integer',
        'score_breakdown' => 'array',
        'scored_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'company',
        'is_lead' => true,
        'status' => 'active',
    ];

    const TYPES = ['individual', 'company'];

    const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'lost'];

    const LEAD_STATUS_TRANSITIONS = [
        'new' => ['contacted'],
        'contacted' => ['qualified', 'lost'],
        'qualified' => ['proposal', 'lost'],
        'proposal' => ['negotiation', 'lost'],
        'negotiation' => ['lost'],
        'lost' => ['new'],
    ];

    const CUSTOMER_STATUSES = ['active', 'at_risk', 'churned', 'paused', 'renewed'];

    const SOURCES = ['web', 'referral', 'cold_call', 'advertisement', 'social_media', 'trade_show', 'other', 'website'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function displayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->type === 'company') {
                    return $this->company_name ?: trim("{$this->first_name} {$this->last_name}");
                }

                return trim("{$this->first_name} {$this->last_name}") ?: $this->company_name;
            },
        );
    }

    public function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function effectiveStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_lead ? $this->lead_status : $this->status,
        );
    }

    public function canTransitionLeadTo(string $newStatus): bool
    {
        return in_array($newStatus, self::LEAD_STATUS_TRANSITIONS[$this->lead_status ?? 'new'] ?? []);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('company_name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('contact_email', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('contacts', 'like', "%{$term}%")
                ->orWhere('addresses', 'like', "%{$term}%");
        });
    }

    public function scopeContactSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('contacts', 'like', "%{$term}%");
        });
    }

    public function scopeAddressSearch(Builder $query, string $term): Builder
    {
        return $query->where('addresses', 'like', "%{$term}%");
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeIsLead(Builder $query, bool $isLead = true): Builder
    {
        return $query->where('is_lead', $isLead);
    }

    public function scopeLeadStatus(Builder $query, string $status): Builder
    {
        return $query->where('lead_status', $status);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeAccountManager(Builder $query, int $userId): Builder
    {
        return $query->where('account_manager_id', $userId);
    }

    public function scopeHealthBelow(Builder $query, int $score): Builder
    {
        return $query->where('health_score', '<', $score);
    }

    public function scopeFollowUpDue(Builder $query): Builder
    {
        return $query->where('next_follow_up_at', '<=', now());
    }

    public function isOverdueForFollowUp(): bool
    {
        return $this->next_follow_up_at !== null && $this->next_follow_up_at->isPast();
    }
}
