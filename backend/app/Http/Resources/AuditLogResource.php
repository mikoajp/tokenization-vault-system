<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vault_id' => $this->vault_id,
            'token_id' => $this->token_id,
            'operation' => $this->operation,
            'result' => $this->result,
            'error_message' => $this->error_message,
            'user_id' => $this->user_id,
            'api_key_id' => $this->api_key_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'request_id' => $this->request_id,
            'processing_time_ms' => $this->processing_time_ms,
            'risk_level' => $this->risk_level,
            'pci_relevant' => $this->pci_relevant,
            'created_at' => $this->created_at,
            'vault' => $this->whenLoaded('vault', function () {
                return [
                    'id' => $this->vault->id,
                    'name' => $this->vault->name,
                    'data_type' => $this->vault->data_type,
                ];
            }),
        ];
    }
}
