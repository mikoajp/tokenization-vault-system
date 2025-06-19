<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VaultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'data_type' => $this->data_type,
            'status' => $this->status,
            'encryption_algorithm' => $this->encryption_algorithm,
            'max_tokens' => $this->max_tokens,
            'current_token_count' => $this->current_token_count,
            'allowed_operations' => $this->allowed_operations,
            'access_restrictions' => $this->access_restrictions,
            'retention_days' => $this->retention_days,
            'key_rotation_interval_days' => $this->key_rotation_interval_days,
            'last_key_rotation' => $this->last_key_rotation,
            'needs_key_rotation' => $this->needsKeyRotation(),
            'capacity_percentage' => round(($this->current_token_count / $this->max_tokens) * 100, 2),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
