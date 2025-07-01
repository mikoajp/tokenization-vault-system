<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Tokenization\Models\Token;
use App\Domain\Tokenization\ValueObjects\TokenId;
use App\Domain\Tokenization\ValueObjects\TokenValue;
use App\Domain\Tokenization\ValueObjects\TokenStatus;
use App\Domain\Tokenization\ValueObjects\TokenType;
use App\Domain\Tokenization\Repositories\TokenRepositoryInterface;
use App\Domain\Tokenization\Exceptions\TokenNotFoundException;
use App\Domain\Vault\ValueObjects\VaultId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentTokenRepository implements TokenRepositoryInterface
{
    public function findById(TokenId $id): ?Token
    {
        return Token::find($id->getValue());
    }

    public function findByIdOrFail(TokenId $id): Token
    {
        $token = $this->findById($id);
        
        if (!$token) {
            throw new TokenNotFoundException($id->getValue());
        }

        return $token;
    }

    public function findByTokenValue(TokenValue $tokenValue): ?Token
    {
        return Token::where('token_value', $tokenValue->getValue())->first();
    }

    public function findByTokenValueOrFail(TokenValue $tokenValue): Token
    {
        $token = $this->findByTokenValue($tokenValue);
        
        if (!$token) {
            throw new TokenNotFoundException($tokenValue->getValue());
        }

        return $token;
    }

    public function findByDataHash(string $dataHash, VaultId $vaultId): ?Token
    {
        return Token::where('data_hash', $dataHash)
                   ->where('vault_id', $vaultId->getValue())
                   ->first();
    }

    public function findByVault(VaultId $vaultId): Collection
    {
        return Token::where('vault_id', $vaultId->getValue())->get();
    }

    public function findByStatus(TokenStatus $status): Collection
    {
        return Token::where('status', $status->getValue())->get();
    }

    public function findByType(TokenType $type): Collection
    {
        return Token::where('token_type', $type->getValue())->get();
    }

    public function findActiveTokens(): Collection
    {
        return Token::active()->get();
    }

    public function findExpiredTokens(): Collection
    {
        return Token::where('expires_at', '<', now())
                   ->where('status', 'active')
                   ->get();
    }

    public function findExpiringTokens(int $hours = 24): Collection
    {
        return Token::expiringWithin($hours)->get();
    }

    public function findUnusedTokens(int $days = 90): Collection
    {
        return Token::unused($days)->get();
    }

    public function save(Token $token): Token
    {
        $token->save();
        return $token->fresh();
    }

    public function delete(TokenId $id): bool
    {
        $token = $this->findById($id);
        
        if (!$token) {
            return false;
        }

        return $token->delete();
    }

    public function paginate(
        int $perPage = 15,
        ?VaultId $vaultId = null,
        ?TokenStatus $status = null,
        ?TokenType $type = null,
        ?string $search = null
    ): LengthAwarePaginator {
        $query = Token::query();

        if ($vaultId) {
            $query->where('vault_id', $vaultId->getValue());
        }

        if ($status) {
            $query->where('status', $status->getValue());
        }

        if ($type) {
            $query->where('token_type', $type->getValue());
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('token_value', 'like', "%{$search}%")
                  ->orWhereJsonContains('metadata', $search);
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getStatistics(VaultId $vaultId): array
    {
        $baseQuery = Token::where('vault_id', $vaultId->getValue());

        return [
            'vault_id' => $vaultId->getValue(),
            'total_tokens' => $baseQuery->count(),
            'active_tokens' => $baseQuery->where('status', 'active')->count(),
            'revoked_tokens' => $baseQuery->where('status', 'revoked')->count(),
            'expired_tokens' => $baseQuery->where('status', 'expired')->count(),
            'compromised_tokens' => $baseQuery->where('status', 'compromised')->count(),
            'tokens_by_type' => $this->getTokensByType($vaultId),
            'usage_statistics' => $this->getUsageStatistics($vaultId),
            'expiration_statistics' => $this->getExpirationStatistics($vaultId),
        ];
    }

    public function getTotalCount(VaultId $vaultId): int
    {
        return Token::where('vault_id', $vaultId->getValue())->count();
    }

    public function getActiveCount(VaultId $vaultId): int
    {
        return Token::where('vault_id', $vaultId->getValue())
                   ->where('status', 'active')
                   ->count();
    }

    public function exists(TokenId $id): bool
    {
        return Token::where('id', $id->getValue())->exists();
    }

    public function existsByTokenValue(TokenValue $tokenValue): bool
    {
        return Token::where('token_value', $tokenValue->getValue())->exists();
    }

    public function bulkUpdateStatus(array $tokenIds, TokenStatus $status): int
    {
        return Token::whereIn('id', $tokenIds)
                   ->update(['status' => $status->getValue()]);
    }

    public function bulkDelete(array $tokenIds): int
    {
        return Token::whereIn('id', $tokenIds)->delete();
    }

    public function searchByMetadata(array $criteria, ?VaultId $vaultId = null): Collection
    {
        $query = Token::query();

        if ($vaultId) {
            $query->where('vault_id', $vaultId->getValue());
        }

        foreach ($criteria as $key => $value) {
            $query->whereJsonContains("metadata->{$key}", $value);
        }

        return $query->get();
    }

    private function getTokensByType(VaultId $vaultId): array
    {
        return Token::where('vault_id', $vaultId->getValue())
                   ->selectRaw('token_type, COUNT(*) as count')
                   ->groupBy('token_type')
                   ->pluck('count', 'token_type')
                   ->toArray();
    }

    private function getUsageStatistics(VaultId $vaultId): array
    {
        $tokens = Token::where('vault_id', $vaultId->getValue())
                      ->selectRaw('
                          AVG(usage_count) as avg_usage,
                          MAX(usage_count) as max_usage,
                          COUNT(CASE WHEN usage_count = 0 THEN 1 END) as unused_count,
                          COUNT(CASE WHEN last_used_at >= ? THEN 1 END) as recently_used_count
                      ', [now()->subDays(30)])
                      ->first();

        return [
            'average_usage' => round($tokens->avg_usage ?? 0, 2),
            'max_usage' => $tokens->max_usage ?? 0,
            'unused_tokens' => $tokens->unused_count ?? 0,
            'recently_used_tokens' => $tokens->recently_used_count ?? 0,
        ];
    }

    private function getExpirationStatistics(VaultId $vaultId): array
    {
        $now = now();
        
        return [
            'expiring_in_24h' => Token::where('vault_id', $vaultId->getValue())
                                     ->where('expires_at', '<=', $now->copy()->addHours(24))
                                     ->where('expires_at', '>', $now)
                                     ->count(),
            'expiring_in_7d' => Token::where('vault_id', $vaultId->getValue())
                                    ->where('expires_at', '<=', $now->copy()->addDays(7))
                                    ->where('expires_at', '>', $now)
                                    ->count(),
            'expired_tokens' => Token::where('vault_id', $vaultId->getValue())
                                    ->where('expires_at', '<', $now)
                                    ->count(),
        ];
    }
}