<?php

namespace App\Http\Resources\AsyncJob;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsyncJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'progress' => $this->progress,
            'input' => $this->when($this->isFinished(), $this->input),
            'result' => $this->when($this->isCompleted(), $this->result),
            'error' => $this->when($this->isFailed(), $this->error),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
