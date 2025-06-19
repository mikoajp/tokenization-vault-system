<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token_value' => $this->token_value,
            'format_preserved_token' => $this->when(
                $this->format_preserved_token,
                $this->format_preserved_token
            ),
            'token_type' => $this->token_type,
            'vault_id' => $this->vault_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'last_used_at' => $this->last_used_at,
            'usage_count' => $this->usage_count,
            'status' => $this->status,
        ];
    }
}
