<?php

namespace App\Models;

use App\Models\Traits\HasCustomFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasCustomFields, HasFactory;

    protected $fillable = [
        'name', 'sku', 'description', 'unit_price',
        'currency', 'category_id', 'tax_id', 'is_active', 'created_by',
        'custom_fields', 'category_custom_fields',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'currency' => 'USD',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_products')
            ->withPivot('id', 'quantity', 'unit_price', 'discount_percent', 'discount_amount', 'notes')
            ->withTimestamps();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        return $q->where(function (Builder $inner) use ($term) {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    public function scopeInCategory(Builder $q, int $categoryId): Builder
    {
        $ids = ProductCategory::allDescendantIds($categoryId);

        return $q->whereIn('category_id', $ids);
    }
}
