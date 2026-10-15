<?php

namespace App\Models\Traits;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeWithTag(Builder $q, string|int $tag): Builder
    {
        return $q->whereHas('tags', fn ($q) =>
            is_numeric($tag) ? $q->where('tags.id', $tag) : $q->where('tags.name', $tag)
        );
    }

    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }
}
