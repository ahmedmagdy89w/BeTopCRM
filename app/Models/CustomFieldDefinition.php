<?php

namespace App\Models;

use App\Services\CustomFieldEntityRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'name', 'label', 'type',
        'options', 'is_required', 'is_filterable', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
    ];

    const TYPES = [
        'text', 'textarea', 'number', 'decimal', 'date', 'datetime',
        'select', 'multi_select', 'boolean', 'color', 'url', 'email', 'phone',
    ];

    /**
     * Types that must be given a non-empty options list.
     */
    const TYPES_REQUIRING_OPTIONS = ['select', 'multi_select'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomFieldGroup::class, 'group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entityType(): ?string
    {
        return $this->group?->entity_type;
    }

    public function scopeForEntity(Builder $q, string $entityType): Builder
    {
        return $q->whereHas('group', fn (Builder $g) => $g->forEntity($entityType));
    }

    public function scopeRequired(Builder $q): Builder
    {
        return $q->where('is_required', true);
    }

    public function scopeFilterable(Builder $q): Builder
    {
        return $q->where('is_filterable', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function requiresOptions(): bool
    {
        return in_array($this->type, self::TYPES_REQUIRING_OPTIONS, true);
    }

    /**
     * The values column this definition's data lives in, for its own scope.
     */
    public function valuesColumn(CustomFieldEntityRegistry $registry): string
    {
        if (! $this->group?->isCategoryScoped()) {
            return 'custom_fields';
        }

        return $registry->categoryValuesColumn($this->group->entity_type);
    }
}
