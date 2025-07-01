<?php

namespace App\Domain\Vault\Events;

use App\Domain\Vault\Models\Vault;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VaultKeyRotated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Vault $vault
    ) {}

    public function getVaultId(): string
    {
        return $this->vault->getId()->getValue();
    }

    public function getRotationTimestamp(): string
    {
        return $this->vault->last_key_rotation->toISOString();
    }
}