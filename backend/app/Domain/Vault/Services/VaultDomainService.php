<?php

namespace App\Domain\Vault\Services;

use App\Domain\Vault\Models\Vault;
use App\Domain\Vault\ValueObjects\VaultId;
use App\Domain\Vault\ValueObjects\VaultStatus;
use App\Domain\Vault\ValueObjects\DataType;
use App\Domain\Vault\ValueObjects\EncryptionConfig;
use App\Domain\Vault\Repositories\VaultRepositoryInterface;
use App\Domain\Vault\Exceptions\VaultNotFoundException;
use App\Domain\Vault\Exceptions\VaultTokenLimitExceededException;

class VaultDomainService
{
    public function __construct(
        private VaultRepositoryInterface $vaultRepository
    ) {}

    public function createVault(
        string $name,
        string $description,
        DataType $dataType,
        EncryptionConfig $encryptionConfig,
        array $allowedOperations = [],
        int $maxTokens = 1000000,
        int $retentionDays = 2555
    ): Vault {
        $vault = new Vault([
            'name' => $name,
            'description' => $description,
            'data_type' => $dataType->getValue(),
            'status' => VaultStatus::active()->getValue(),
            'encryption_algorithm' => $encryptionConfig->getAlgorithm(),
            'encryption_key_reference' => $encryptionConfig->getKeyReference(),
            'max_tokens' => $maxTokens,
            'allowed_operations' => $allowedOperations,
            'retention_days' => $retentionDays,
            'key_rotation_interval_days' => 365,
            'current_token_count' => 0,
        ]);

        return $this->vaultRepository->save($vault);
    }

    public function getVault(VaultId $vaultId): Vault
    {
        $vault = $this->vaultRepository->findById($vaultId);
        
        if (!$vault) {
            throw new VaultNotFoundException($vaultId->getValue());
        }

        return $vault;
    }

    public function updateVault(VaultId $vaultId, array $updateData): Vault
    {
        $vault = $this->getVault($vaultId);

        $vault->fill($updateData);

        return $this->vaultRepository->save($vault);
    }

    public function activateVault(VaultId $vaultId): Vault
    {
        $vault = $this->getVault($vaultId);
        $vault->activate();

        return $this->vaultRepository->save($vault);
    }

    public function deactivateVault(VaultId $vaultId): Vault
    {
        $vault = $this->getVault($vaultId);
        $vault->deactivate();

        return $this->vaultRepository->save($vault);
    }

    public function archiveVault(VaultId $vaultId): Vault
    {
        $vault = $this->getVault($vaultId);
        $vault->archive();

        return $this->vaultRepository->save($vault);
    }

    public function rotateVaultKey(VaultId $vaultId): Vault
    {
        $vault = $this->getVault($vaultId);
        $vault->rotateKey();

        return $this->vaultRepository->save($vault);
    }

    public function canVaultAcceptTokens(VaultId $vaultId): bool
    {
        $vault = $this->getVault($vaultId);
        return $vault->canAcceptNewTokens();
    }

    public function validateVaultForOperation(VaultId $vaultId, string $operation): Vault
    {
        $vault = $this->getVault($vaultId);

        if (!$vault->getStatus()->isActive()) {
            throw new VaultNotFoundException($vaultId->getValue());
        }

        if (!$vault->isOperationAllowed($operation)) {
            throw new \InvalidArgumentException(
                "Operation '{$operation}' is not allowed for vault {$vault->name}"
            );
        }

        return $vault;
    }

    public function getVaultStatistics(VaultId $vaultId): array
    {
        return $this->vaultRepository->getStatistics($vaultId);
    }

    public function findVaultsNeedingKeyRotation(): array
    {
        return $this->vaultRepository->findVaultsNeedingKeyRotation()->toArray();
    }
}