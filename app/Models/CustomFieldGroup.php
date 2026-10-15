<?php

namespace App\Models;

use App\Services\CustomFieldEntityRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFieldGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type', 'scope', 'category_id', 'scope_key',
        'name', 'description', 'created_by',
    ];

    protected $casts = [
        'category_id' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class, 'group_id')->ordered();
    }

    /**
     * Only meaningful when the group has category scope; absent otherwise.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function scopeForTarget(Builder $q, string $entityType, string $scopeKey): Builder
    {
        return $q->where('entity_type', $entityType)->where('scope_key', $scopeKey);
    }

    public function scopeForEntity(Builder $q, string $entityType): Builder
    {
        return $q->where('entity_type', $entityType)
            ->where('scope', CustomFieldEntityRegistry::SCOPE_ENTITY);
    }

    public function scopeForCategory(Builder $q, int $categoryId): Builder
    {
        return $q->where('category_id', $categoryId);
    }

    public function isCategoryScoped(): bool
    {
        return $this->scope === CustomFieldEntityRegistry::SCOPE_CATEGORY;
    }

    /**
     * Keep scope_key in step with scope/category_id so the one-group-per-target
     * unique index can never be bypassed by a partial write.
     */
    protected static function booted(): void
    {
        static::saving(function (self $group): void {
            $group->scope_key = CustomFieldEntityRegistry::scopeKey(
                $group->scope ?? CustomFieldEntityRegistry::SCOPE_ENTITY,
                $group->category_id !== null ? (int) $group->category_id : null,
            );
        });
    }
}
