<?php

namespace App\Http\Resources\WhatsApp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppPreparedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'entity_type' => $this->resource['entity_type'],
            'entity_id' => $this->resource['entity_id'],
            'customer_id' => $this->resource['customer_id'],
            'opportunity_id' => $this->resource['opportunity_id'],
            'phone' => $this->resource['phone'],
            'recipient_name' => $this->resource['recipient_name'],
            'document_number' => $this->resource['document_number'],
            'total' => $this->resource['total'],
            'currency' => $this->resource['currency'],
            'pdf_url' => $this->resource['pdf_url'],
            'message' => $this->resource['message'],
            'click_to_chat_url' => $this->resource['click_to_chat_url'],
            'cloud_api_available' => $this->resource['cloud_api_available'],
        ];
    }
}
