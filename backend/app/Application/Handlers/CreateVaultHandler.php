<?php

namespace App\Application\Handlers;

use App\Application\Commands\CreateVaultCommand;
use App\Application\DTOs\VaultDTO;
use App\Domain\Vault\Services\VaultDomainService;
use App\Domain\Vault\ValueObjects\DataType;
use App\Domain\Vault\ValueObjects\EncryptionConfig;
use App\Infrastructure\Services\EncryptionService;

class CreateVaultHandler
{
    public function __construct(
        private VaultDomainService $vaultDomainService,
        private EncryptionService $encryptionService
    ) {}

    public function handle(CreateVaultCommand $command): VaultDTO
    {
        // Generate encryption key reference
        $keyReference = $this->encryptionService->generateKeyReference();
        
        $encryptionConfig = new EncryptionConfig(
            $command->encryptionAlgorithm,
            $keyReference
        );

        $vault = $this->vaultDomainService->createVault(
            name: $command->name,
            description: $command->description ?? '',
            dataType: new DataType($command->dataType),
            encryptionConfig: $encryptionConfig,
            allowedOperations: $command->allowedOperations,
            maxTokens: $command->maxTokens,
            retentionDays: $command->retentionDays
        );

        return VaultDTO::fromModel($vault);
    }
}