<?php

namespace App\Http\Resources\CustomerActivity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerActivityCollection extends ResourceCollection
{
    public $collects = CustomerActivityResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
