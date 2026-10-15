<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TagService
{
    public function list(): Collection
    {
        return Tag::with('creator:id,name')->orderBy('name')->get();
    }

    public function create(array $data, User $user): Tag
    {
        $data['created_by'] = $user->id;
        return Tag::create($data);
    }

    public function update(Tag $tag, array $data): Tag
    {
        $tag->update($data);
        return $tag->fresh();
    }

    public function delete(Tag $tag): void
    {
        $tag->delete();
    }
}
