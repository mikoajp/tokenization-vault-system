<?php

namespace App\Domain\Vault\Events;

use App\Domain\Vault\Models\Vault;
use App\Domain\Vault\ValueObjects\VaultStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VaultStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Vault $vault,
        public readonly VaultStatus $oldStatus,
        public readonly VaultStatus $newStatus
    ) {}

    public function getVaultId(): string
    {
        return $this->vault->getId()->getValue();
    }

    public function getOldStatus(): string
    {
        return $this->oldStatus->getValue();
    }

    public function getNewStatus(): string
    {
        return $this->newStatus->getValue();
    }

    public function isActivation(): bool
    {
        return $this->newStatus->isActive();
    }

    public function isDeactivation(): bool
    {
        return $this->oldStatus->isActive() && !$this->newStatus->isActive();
    }

    public function isArchiving(): bool
    {
        return $this->newStatus->isArchived();
    }
}