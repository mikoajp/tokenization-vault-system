<?php

namespace App\Domain\Tokenization\Services;

use App\Domain\Tokenization\Models\Token;
use App\Domain\Tokenization\ValueObjects\TokenId;
use App\Domain\Tokenization\ValueObjects\TokenValue;
use App\Domain\Tokenization\ValueObjects\TokenStatus;
use App\Domain\Tokenization\ValueObjects\TokenType;
use App\Domain\Tokenization\Repositories\TokenRepositoryInterface;
use App\Domain\Tokenization\Exceptions\TokenNotFoundException;
use App\Domain\Tokenization\Exceptions\TokenNotUsableException;
use App\Domain\Vault\ValueObjects\VaultId;
use App\Domain\Vault\Services\VaultDomainService;
use App\Infrastructure\Services\EncryptionService;
use Carbon\Carbon;

class TokenizationDomainService
{
    public function __construct(
        private TokenRepositoryInterface $tokenRepository,
        private VaultDomainService $vaultDomainService,
        private EncryptionService $encryptionService
    ) {}

    public function createToken(
        VaultId $vaultId,
        string $sensitiveData,
        TokenType $tokenType,
        array $metadata = [],
        ?Carbon $expiresAt = null
    ): Token {
        // Validate vault can accept tokens
        $vault = $this->vaultDomainService->validateVaultForOperation($vaultId, 'tokenize');

        // Check for existing token (deduplication)
        $dataHash = $this->encryptionService->generateDataHash($sensitiveData);
        $existingToken = $this->tokenRepository->findByDataHash($dataHash, $vaultId);

        if ($existingToken && $existingToken->isUsable()) {
            $existingToken->recordUsage();
            return $existingToken;
        }

        // Generate token value
        $tokenValue = $this->generateTokenValue($sensitiveData, $tokenType);

        // Encrypt sensitive data
        $encryptedData = $this->encryptionService->encrypt($sensitiveData, $vault->getEncryptionConfig());

        // Create token
        $token = new Token([
            'vault_id' => $vaultId->getValue(),
            'token_value' => $tokenValue,
            'format_preserved_token' => $tokenType->isFormatPreserving() ? $this->generateFormatPreservingToken($sensitiveData) : null,
            'token_type' => $tokenType->getValue(),
            'metadata' => $metadata,
            'expires_at' => $expiresAt,
            'key_version' => $vault->getActiveKey()?->key_version ?? 1,
            'status' => TokenStatus::active()->getValue(),
            'encrypted_data' => $encryptedData,
            'data_hash' => $dataHash,
            'checksum' => $this->encryptionService->generateChecksum($sensitiveData),
            'usage_count' => 0,
        ]);

        $savedToken = $this->tokenRepository->save($token);

        // Update vault token count
        $vault->incrementTokenCount();

        return $savedToken;
    }

    public function detokenize(TokenValue $tokenValue): string
    {
        $token = $this->tokenRepository->findByTokenValue($tokenValue);

        if (!$token) {
            throw new TokenNotFoundException($tokenValue->getValue());
        }

        if (!$token->isUsable()) {
            throw new TokenNotUsableException("Token is not usable for detokenization");
        }

        // Validate vault allows detokenization
        $vault = $this->vaultDomainService->validateVaultForOperation(
            $token->getVaultId(), 
            'detokenize'
        );

        // Record usage
        $token->recordUsage();

        // Decrypt data
        return $this->encryptionService->decrypt(
            $token->encrypted_data, 
            $vault->getEncryptionConfig()
        );
    }

    public function revokeToken(TokenId $tokenId, string $reason = null): Token
    {
        $token = $this->getToken($tokenId);
        $token->revoke($reason);

        return $this->tokenRepository->save($token);
    }

    public function expireToken(TokenId $tokenId): Token
    {
        $token = $this->getToken($tokenId);
        $token->expire();

        return $this->tokenRepository->save($token);
    }

    public function markTokenCompromised(TokenId $tokenId, string $reason = null): Token
    {
        $token = $this->getToken($tokenId);
        $token->markCompromised($reason);

        return $this->tokenRepository->save($token);
    }

    public function getToken(TokenId $tokenId): Token
    {
        $token = $this->tokenRepository->findById($tokenId);

        if (!$token) {
            throw new TokenNotFoundException($tokenId->getValue());
        }

        return $token;
    }

    public function searchTokens(
        VaultId $vaultId,
        array $searchCriteria,
        int $limit = 100
    ): array {
        // Validate vault allows search
        $this->vaultDomainService->validateVaultForOperation($vaultId, 'search');

        $tokens = $this->tokenRepository->searchByMetadata($searchCriteria, $vaultId);

        return $tokens->take($limit)->toArray();
    }

    public function bulkTokenize(
        VaultId $vaultId,
        array $dataItems,
        TokenType $tokenType,
        array $commonMetadata = []
    ): array {
        // Validate vault allows bulk operations
        $this->vaultDomainService->validateVaultForOperation($vaultId, 'bulk_tokenize');

        $results = [];

        foreach ($dataItems as $index => $dataItem) {
            try {
                $metadata = array_merge($commonMetadata, [
                    'batch_index' => $index,
                    'batch_id' => \Illuminate\Support\Str::uuid(),
                ]);

                $token = $this->createToken(
                    $vaultId,
                    $dataItem['data'],
                    $tokenType,
                    array_merge($metadata, $dataItem['metadata'] ?? [])
                );

                $results[] = [
                    'index' => $index,
                    'status' => 'success',
                    'token_value' => $token->getTokenValue()->getValue(),
                    'token_id' => $token->getId()->getValue(),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $index,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function bulkDetokenize(array $tokenValues): array
    {
        $results = [];

        foreach ($tokenValues as $index => $tokenValueString) {
            try {
                $tokenValue = new TokenValue($tokenValueString);
                $sensitiveData = $this->detokenize($tokenValue);

                $results[] = [
                    'index' => $index,
                    'status' => 'success',
                    'token_value' => $tokenValueString,
                    'data' => $sensitiveData,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $index,
                    'status' => 'error',
                    'token_value' => $tokenValueString,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function getTokenStatistics(VaultId $vaultId): array
    {
        return $this->tokenRepository->getStatistics($vaultId);
    }

    public function cleanupExpiredTokens(): int
    {
        $expiredTokens = $this->tokenRepository->findExpiredTokens();
        $count = 0;

        foreach ($expiredTokens as $token) {
            if ($token->getStatus()->isActive()) {
                $token->expire();
                $this->tokenRepository->save($token);
                $count++;
            }
        }

        return $count;
    }

    private function generateTokenValue(string $sensitiveData, TokenType $tokenType): string
    {
        switch ($tokenType->getValue()) {
            case 'random':
                return $this->generateRandomToken();
            
            case 'format_preserving':
                return $this->generateFormatPreservingToken($sensitiveData);
            
            case 'sequential':
                return $this->generateSequentialToken();
            
            default:
                return $this->generateRandomToken();
        }
    }

    private function generateRandomToken(int $length = 32): string
    {
        $charset = config('tokenization.algorithms.random.charset', 
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        
        return \Illuminate\Support\Str::random($length);
    }

    private function generateFormatPreservingToken(string $sensitiveData): string
    {
        // Simplified format-preserving tokenization
        // In production, use proper FPE algorithms like FF1/FF3
        $length = strlen($sensitiveData);
        $pattern = preg_replace('/\d/', 'N', $sensitiveData);
        $pattern = preg_replace('/[A-Za-z]/', 'A', $pattern);
        
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === 'N') {
                $token .= rand(0, 9);
            } elseif ($char === 'A') {
                $token .= chr(rand(65, 90)); // A-Z
            } else {
                $token .= $char;
            }
        }
        
        return $token;
    }

    private function generateSequentialToken(): string
    {
        $startValue = config('tokenization.algorithms.sequential.start_value', 1000000);
        $lastSequence = \Illuminate\Support\Facades\Cache::get('token_sequence', $startValue);
        $newSequence = $lastSequence + 1;
        
        \Illuminate\Support\Facades\Cache::put('token_sequence', $newSequence);
        
        return (string) $newSequence;
    }
}