<?php

namespace App\Domain\Vault\Repositories;

use App\Domain\Vault\Models\Vault;
use App\Domain\Vault\ValueObjects\VaultId;
use App\Domain\Vault\ValueObjects\DataType;
use App\Domain\Vault\ValueObjects\VaultStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface VaultRepositoryInterface
{
    public function findById(VaultId $id): ?Vault;

    public function findByIdOrFail(VaultId $id): Vault;

    public function findByName(string $name): ?Vault;

    public function findByDataType(DataType $dataType): Collection;

    public function findByStatus(VaultStatus $status): Collection;

    public function findActiveVaults(): Collection;

    public function findVaultsNeedingKeyRotation(): Collection;

    public function save(Vault $vault): Vault;

    public function delete(VaultId $id): bool;

    public function paginate(
        int $perPage = 15,
        ?DataType $dataType = null,
        ?VaultStatus $status = null,
        ?string $search = null
    ): LengthAwarePaginator;

    public function getStatistics(VaultId $id): array;

    public function getTotalTokenCount(VaultId $id): int;

    public function exists(VaultId $id): bool;
}