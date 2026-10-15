<?php

namespace App\Http\Resources\Note;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'body'         => $this->body,
            'is_pinned'    => $this->is_pinned,
            'parent_id'    => $this->parent_id,
            'author'       => $this->when($this->relationLoaded('author'), fn () => [
                'id'   => $this->author->id,
                'name' => $this->author->name,
            ]),
            'replies_count' => $this->when(
                $this->relationLoaded('replies'),
                fn () => $this->replies->count(),
                fn () => $this->replies_count ?? 0
            ),
            'replies'      => $this->when(
                $this->relationLoaded('replies') && $this->parent_id === null,
                fn () => NoteResource::collection($this->replies)
            ),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
