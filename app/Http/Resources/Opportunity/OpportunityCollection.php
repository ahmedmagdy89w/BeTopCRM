<?php

namespace App\Http\Resources\Opportunity;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OpportunityCollection extends ResourceCollection
{
    public $collects = OpportunityResource::class;
}
