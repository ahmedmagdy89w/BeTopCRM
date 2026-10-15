<?php

namespace App\Models\Traits;

use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotes
{
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function pinnedNotes(): MorphMany
    {
        return $this->notes()->where('is_pinned', true);
    }
}
