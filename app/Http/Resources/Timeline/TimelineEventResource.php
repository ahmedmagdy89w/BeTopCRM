<?php

namespace App\Http\Resources\Timeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->resource['id'],
            'type'        => $this->resource['type'],
            'subtype'     => $this->resource['subtype'] ?? null,
            'title'       => $this->resource['title'],
            'description' => $this->resource['description'] ?? null,
            'user'        => $this->resource['user'] ?? null,
            'metadata'    => $this->resource['metadata'] ?? [],
            'timestamp'   => $this->resource['timestamp'],
        ];
    }
}
