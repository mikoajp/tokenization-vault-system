<?php

namespace App\Application\Services;

use App\Application\Commands\CreateVaultCommand;
use App\Application\Commands\UpdateVaultCommand;
use App\Application\Commands\RotateVaultKeyCommand;
use App\Application\Handlers\CreateVaultHandler;
use App\Application\DTOs\VaultDTO;
use App\Domain\Vault\Services\VaultDomainService;
use App\Domain\Vault\ValueObjects\VaultId;
use App\Domain\Vault\ValueObjects\DataType;
use App\Domain\Vault\ValueObjects\VaultStatus;
use App\Domain\Vault\Repositories\VaultRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VaultApplicationService
{
    public function __construct(
        private VaultDomainService $vaultDomainService,
        private VaultRepositoryInterface $vaultRepository,
        private CreateVaultHandler $createVaultHandler
    ) {}

    public function createVault(CreateVaultCommand $command): VaultDTO
    {
        return $this->createVaultHandler->handle($command);
    }

    public function updateVault(UpdateVaultCommand $command): VaultDTO
    {
        $vaultId = new VaultId($command->vaultId);
        $vault = $this->vaultDomainService->updateVault($vaultId, $command->getUpdateData());

        return VaultDTO::fromModel($vault);
    }

    public function getVault(string $vaultId): VaultDTO
    {
        $vault = $this->vaultDomainService->getVault(new VaultId($vaultId));
        return VaultDTO::fromModel($vault);
    }

    public function getVaults(
        int $perPage = 15,
        ?string $dataType = null,
        ?string $status = null,
        ?string $search = null
    ): LengthAwarePaginator {
        $dataTypeVO = $dataType ? new DataType($dataType) : null;
        $statusVO = $status ? new VaultStatus($status) : null;

        return $this->vaultRepository->paginate($perPage, $dataTypeVO, $statusVO, $search);
    }

    public function rotateVaultKey(RotateVaultKeyCommand $command): VaultDTO
    {
        $vaultId = new VaultId($command->vaultId);
        $vault = $this->vaultDomainService->rotateVaultKey($vaultId);

        return VaultDTO::fromModel($vault);
    }

    public function activateVault(string $vaultId): VaultDTO
    {
        $vault = $this->vaultDomainService->activateVault(new VaultId($vaultId));
        return VaultDTO::fromModel($vault);
    }

    public function deactivateVault(string $vaultId): VaultDTO
    {
        $vault = $this->vaultDomainService->deactivateVault(new VaultId($vaultId));
        return VaultDTO::fromModel($vault);
    }

    public function archiveVault(string $vaultId): VaultDTO
    {
        $vault = $this->vaultDomainService->archiveVault(new VaultId($vaultId));
        return VaultDTO::fromModel($vault);
    }

    public function getVaultStatistics(string $vaultId): array
    {
        return $this->vaultDomainService->getVaultStatistics(new VaultId($vaultId));
    }

    public function getVaultsNeedingKeyRotation(): array
    {
        return $this->vaultDomainService->findVaultsNeedingKeyRotation();
    }
}