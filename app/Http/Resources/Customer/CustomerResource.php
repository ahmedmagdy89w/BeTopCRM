<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\Opportunity\OpportunityResource;
use App\Http\Resources\Tag\TagResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'is_lead' => (bool) $this->is_lead,
            'lead_status' => $this->lead_status,
            'status' => $this->status,
            'effective_status' => $this->effective_status,
            'display_name' => $this->display_name,
            // Individual
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'birthday' => $this->birthday?->toDateString(),
            'gender' => $this->gender,
            // Company
            'company_name' => $this->company_name,
            'industry' => $this->industry,
            'website' => $this->website,
            'tax_id' => $this->tax_id,
            // Legacy snapshot (kept for BC)
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            // Pipeline
            'source' => $this->source,
            'estimated_value' => $this->estimated_value,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'description' => $this->description,
            'assigned_to' => $this->when($this->relationLoaded('assignee') && $this->assignee, fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'created_by' => $this->when($this->relationLoaded('creator'), fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'account_manager' => $this->when($this->relationLoaded('accountManager') && $this->accountManager, fn () => [
                'id' => $this->accountManager->id,
                'name' => $this->accountManager->name,
            ]),
            'contract_value' => $this->contract_value,
            'health_score' => $this->health_score,
            'last_contacted_at' => $this->last_contacted_at,
            'next_follow_up_at' => $this->next_follow_up_at,
            'notes' => $this->notes,
            'converted_at' => $this->converted_at,
            'contacts' => $this->contacts ?? [],
            'addresses' => $this->addresses ?? [],
            'score' => $this->score,
            'score_breakdown' => $this->score_breakdown,
            'scored_at' => $this->scored_at,
            'custom_fields' => $this->custom_fields,
            'activities_count' => $this->when(isset($this->activities_count), $this->activities_count),
            'opportunities_count' => $this->when(isset($this->opportunities_count), $this->opportunities_count),
            'opportunities' => OpportunityResource::collection($this->whenLoaded('opportunities')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
