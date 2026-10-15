<?php

namespace App\Http\Resources\Import;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'entity_type'       => $this->entity_type,
            'original_filename' => $this->original_filename,
            'status'            => $this->status,
            'total_rows'        => $this->total_rows,
            'processed_rows'    => $this->processed_rows,
            'successful_rows'   => $this->successful_rows,
            'failed_rows'       => $this->failed_rows,
            'errors'            => $this->errors,
            'completed_at'      => $this->completed_at,
            'created_at'        => $this->created_at,
        ];
    }
}
