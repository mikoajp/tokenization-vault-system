<?php

namespace App\Domain\Vault\Events;

use App\Domain\Vault\Models\Vault;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VaultCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Vault $vault
    ) {}

    public function getVaultId(): string
    {
        return $this->vault->getId()->getValue();
    }

    public function getVaultName(): string
    {
        return $this->vault->name;
    }

    public function getDataType(): string
    {
        return $this->vault->getDataType()->getValue();
    }
}