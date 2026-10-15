<?php

namespace App\Http\Resources\OpportunityActivity;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OpportunityActivityCollection extends ResourceCollection
{
    public $collects = OpportunityActivityResource::class;
}
