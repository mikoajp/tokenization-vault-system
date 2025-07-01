<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Vault\Models\Vault;
use App\Domain\Vault\ValueObjects\VaultId;
use App\Domain\Vault\ValueObjects\DataType;
use App\Domain\Vault\ValueObjects\VaultStatus;
use App\Domain\Vault\Repositories\VaultRepositoryInterface;
use App\Domain\Vault\Exceptions\VaultNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentVaultRepository implements VaultRepositoryInterface
{
    public function findById(VaultId $id): ?Vault
    {
        return Vault::find($id->getValue());
    }

    public function findByIdOrFail(VaultId $id): Vault
    {
        $vault = $this->findById($id);
        
        if (!$vault) {
            throw new VaultNotFoundException($id->getValue());
        }

        return $vault;
    }

    public function findByName(string $name): ?Vault
    {
        return Vault::where('name', $name)->first();
    }

    public function findByDataType(DataType $dataType): Collection
    {
        return Vault::where('data_type', $dataType->getValue())->get();
    }

    public function findByStatus(VaultStatus $status): Collection
    {
        return Vault::where('status', $status->getValue())->get();
    }

    public function findActiveVaults(): Collection
    {
        return Vault::active()->get();
    }

    public function findVaultsNeedingKeyRotation(): Collection
    {
        return Vault::needingKeyRotation()->get();
    }

    public function save(Vault $vault): Vault
    {
        $vault->save();
        return $vault->fresh();
    }

    public function delete(VaultId $id): bool
    {
        $vault = $this->findById($id);
        
        if (!$vault) {
            return false;
        }

        return $vault->delete();
    }

    public function paginate(
        int $perPage = 15,
        ?DataType $dataType = null,
        ?VaultStatus $status = null,
        ?string $search = null
    ): LengthAwarePaginator {
        $query = Vault::query();

        if ($dataType) {
            $query->where('data_type', $dataType->getValue());
        }

        if ($status) {
            $query->where('status', $status->getValue());
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getStatistics(VaultId $id): array
    {
        $vault = $this->findByIdOrFail($id);

        return [
            'vault_id' => $id->getValue(),
            'name' => $vault->name,
            'status' => $vault->status,
            'data_type' => $vault->data_type,
            'current_token_count' => $vault->current_token_count,
            'max_tokens' => $vault->max_tokens,
            'token_usage_percentage' => round(($vault->current_token_count / $vault->max_tokens) * 100, 2),
            'created_at' => $vault->created_at,
            'last_key_rotation' => $vault->last_key_rotation,
            'needs_key_rotation' => $vault->needsKeyRotation(),
            'allowed_operations' => $vault->allowed_operations,
            'retention_days' => $vault->retention_days,
            'recent_activity' => $this->getRecentActivity($id),
        ];
    }

    public function getTotalTokenCount(VaultId $id): int
    {
        $vault = $this->findByIdOrFail($id);
        return $vault->current_token_count;
    }

    public function exists(VaultId $id): bool
    {
        return Vault::where('id', $id->getValue())->exists();
    }

    private function getRecentActivity(VaultId $vaultId): array
    {
        return DB::table('audit_logs')
            ->where('vault_id', $vaultId->getValue())
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('
                operation,
                COUNT(*) as count,
                MAX(created_at) as last_occurrence
            ')
            ->groupBy('operation')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }
}