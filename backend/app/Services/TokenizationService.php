<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\Token;
use App\Exceptions\TokenizationException;
use App\Exceptions\VaultException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TokenizationService
{
    public function __construct(
        private EncryptionService $encryptionService,
        private AuditService $auditService
    ) {}

    /**
     * Tokenize sensitive data
     */
    public function tokenize(string $vaultId, string $sensitiveData, array $metadata = []): array
    {
        return DB::transaction(function () use ($vaultId, $sensitiveData, $metadata) {
            $startTime = microtime(true);

            try {
                // Validate vault
                $vault = $this->validateVault($vaultId, 'tokenize');

                // Check for existing token (deduplication)
                $dataHash = $this->encryptionService->generateDataHash($sensitiveData);
                $existingToken = $this->findExistingToken($vault, $dataHash);

                if ($existingToken) {
                    $existingToken->recordUsage();
                    $this->logTokenizationEvent($vault, $existingToken, 'tokenize', 'success', $startTime);
                    return $this->formatTokenResponse($existingToken, false); // false = not new
                }

                // Check vault capacity
                if ($vault->hasReachedTokenLimit()) {
                    throw new VaultException("Vault has reached maximum token capacity: {$vault->max_tokens}");
                }

                // Get active encryption key
                $activeKey = $vault->getActiveKey();
                if (!$activeKey) {
                    throw new VaultException("No active encryption key found for vault: {$vault->id}");
                }

                // Encrypt the data
                $encryptedData = $this->encryptionService->encrypt($sensitiveData, $activeKey->key_version);

                // Generate tokens
                $tokenValue = $this->generateTokenValue($vault);
                $formatPreservedToken = null;

                if (in_array('format_preserving', $vault->allowed_operations ?? [])) {
                    $formatPreservedToken = $this->encryptionService->generateFormatPreservingToken($sensitiveData);
                }

                // Generate checksum
                $checksum = $this->encryptionService->generateChecksum($tokenValue, $dataHash);

                // Create token record
                $token = Token::create([
                    'vault_id' => $vault->id,
                    'token_value' => $tokenValue,
                    'encrypted_data' => json_encode($encryptedData),
                    'data_hash' => $dataHash,
                    'format_preserved_token' => $formatPreservedToken,
                    'token_type' => $formatPreservedToken ? 'format_preserving' : 'random',
                    'metadata' => array_merge($metadata, [
                        'created_by' => auth()->id() ?? request()->header('X-User-ID'),
                        'source_ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]),
                    'checksum' => $checksum,
                    'key_version' => $activeKey->key_version,
                    'status' => 'active',
                    'expires_at' => $this->calculateExpirationDate($vault, $metadata),
                ]);

                // Update vault statistics
                $vault->incrementTokenCount();

                // Log successful tokenization
                $this->logTokenizationEvent($vault, $token, 'tokenize', 'success', $startTime);

                return $this->formatTokenResponse($token, true); // true = new token

            } catch (\Exception $e) {
                $this->logTokenizationEvent($vault ?? null, null, 'tokenize', 'failure', $startTime, $e->getMessage());
                throw new TokenizationException('Tokenization failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Detokenize (retrieve original data)
     */
    public function detokenize(string $vaultId, string $tokenValue): string
    {
        $startTime = microtime(true);

        try {
            // Validate vault
            $vault = $this->validateVault($vaultId, 'detokenize');

            // Find token
            $token = Token::byVault($vaultId)
                ->byTokenValue($tokenValue)
                ->active()
                ->notExpired()
                ->first();

            if (!$token) {
                throw new TokenizationException('Token not found or expired');
            }

            // Verify token integrity
            if (!$this->verifyTokenIntegrity($token)) {
                $token->markCompromised();
                throw new TokenizationException('Token integrity verification failed');
            }

            // Decrypt data
            $encryptedData = json_decode($token->encrypted_data, true);
            $originalData = $this->encryptionService->decrypt($encryptedData);

            // Record usage
            $token->recordUsage();

            // Log successful detokenization
            $this->logTokenizationEvent($vault, $token, 'detokenize', 'success', $startTime);

            return $originalData;

        } catch (\Exception $e) {
            $this->logTokenizationEvent($vault ?? null, $token ?? null, 'detokenize', 'failure', $startTime, $e->getMessage());
            throw new TokenizationException('Detokenization failed: ' . $e->getMessage());
        }
    }

    /**
     * Search tokens by metadata
     */
    public function search(string $vaultId, array $criteria, int $limit = 100): array
    {
        $startTime = microtime(true);

        try {
            // Validate vault
            $vault = $this->validateVault($vaultId, 'search');

            $query = Token::byVault($vaultId)->active()->notExpired();

            // Apply search criteria
            foreach ($criteria as $field => $value) {
                if ($field === 'metadata') {
                    foreach ($value as $metaKey => $metaValue) {
                        $query->whereJsonContains("metadata->{$metaKey}", $metaValue);
                    }
                } elseif ($field === 'created_after') {
                    $query->where('created_at', '>=', $value);
                } elseif ($field === 'created_before') {
                    $query->where('created_at', '<=', $value);
                } elseif ($field === 'token_type') {
                    $query->where('token_type', $value);
                }
            }

            $tokens = $query->limit($limit)->get();

            $results = $tokens->map(function ($token) {
                return [
                    'token_value' => $token->token_value,
                    'format_preserved_token' => $token->format_preserved_token,
                    'token_type' => $token->token_type,
                    'metadata' => $token->metadata,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                    'usage_count' => $token->usage_count,
                ];
            });

            // Log search operation
            $this->logTokenizationEvent($vault, null, 'search', 'success', $startTime, null, [
                'criteria' => $criteria,
                'results_count' => $results->count(),
            ]);

            return $results->toArray();

        } catch (\Exception $e) {
            $this->logTokenizationEvent($vault ?? null, null, 'search', 'failure', $startTime, $e->getMessage());
            throw new TokenizationException('Search failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk tokenization for high-volume operations
     */
    public function bulkTokenize(string $vaultId, array $dataItems, array $globalMetadata = []): array
    {
        $startTime = microtime(true);
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        try {
            $vault = $this->validateVault($vaultId, 'bulk_tokenize');

            DB::transaction(function () use ($vault, $dataItems, $globalMetadata, &$results, &$successCount, &$failureCount) {
                foreach ($dataItems as $index => $item) {
                    try {
                        $data = is_array($item) ? $item['data'] : $item;
                        $metadata = is_array($item) ? array_merge($globalMetadata, $item['metadata'] ?? []) : $globalMetadata;

                        $result = $this->tokenize($vault->id, $data, $metadata);
                        $results[$index] = array_merge($result, ['status' => 'success']);
                        $successCount++;

                    } catch (\Exception $e) {
                        $results[$index] = [
                            'status' => 'failure',
                            'error' => $e->getMessage(),
                        ];
                        $failureCount++;
                    }
                }
            });

            // Log bulk operation
            $this->logTokenizationEvent($vault, null, 'bulk_tokenize', 'success', $startTime, null, [
                'total_items' => count($dataItems),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);

            return [
                'results' => $results,
                'summary' => [
                    'total' => count($dataItems),
                    'success' => $successCount,
                    'failures' => $failureCount,
                ],
            ];

        } catch (\Exception $e) {
            $this->logTokenizationEvent($vault ?? null, null, 'bulk_tokenize', 'failure', $startTime, $e->getMessage());
            throw new TokenizationException('Bulk tokenization failed: ' . $e->getMessage());
        }
    }

    /**
     * Revoke a token
     */
    public function revokeToken(string $vaultId, string $tokenValue): bool
    {
        $startTime = microtime(true);

        try {
            $vault = $this->validateVault($vaultId, 'revoke');

            $token = Token::byVault($vaultId)
                ->byTokenValue($tokenValue)
                ->active()
                ->first();

            if (!$token) {
                throw new TokenizationException('Token not found');
            }

            $token->revoke();
            $vault->decrementTokenCount();

            $this->logTokenizationEvent($vault, $token, 'token_revoke', 'success', $startTime);

            return true;

        } catch (\Exception $e) {
            $this->logTokenizationEvent($vault ?? null, null, 'token_revoke', 'failure', $startTime, $e->getMessage());
            throw new TokenizationException('Token revocation failed: ' . $e->getMessage());
        }
    }

    // Private helper methods

    private function validateVault(string $vaultId, string $operation): Vault
    {
        $vault = Vault::find($vaultId);

        if (!$vault) {
            throw new VaultException("Vault not found: {$vaultId}");
        }

        if ($vault->status !== 'active') {
            throw new VaultException("Vault is not active: {$vault->status}");
        }

        if (!$vault->isOperationAllowed($operation)) {
            throw new VaultException("Operation '{$operation}' not allowed for this vault");
        }

        return $vault;
    }

    private function findExistingToken(Vault $vault, string $dataHash): ?Token
    {
        return Token::byVault($vault->id)
            ->where('data_hash', $dataHash)
            ->active()
            ->notExpired()
            ->first();
    }

    private function generateTokenValue(Vault $vault): string
    {
        $prefix = match($vault->data_type) {
            'card' => 'card',
            'ssn' => 'ssn',
            'bank_account' => 'bank',
            default => 'tok',
        };

        do {
            $tokenValue = $prefix . '_' . Str::random(32);
        } while (Token::where('token_value', $tokenValue)->exists());

        return $tokenValue;
    }

    private function calculateExpirationDate(Vault $vault, array $metadata): ?string
    {
        if (isset($metadata['expires_in_days'])) {
            return now()->addDays($metadata['expires_in_days'])->toDateTimeString();
        }

        if ($vault->retention_days > 0) {
            return now()->addDays($vault->retention_days)->toDateTimeString();
        }

        return null;
    }

    private function verifyTokenIntegrity(Token $token): bool
    {
        $expectedChecksum = $this->encryptionService->generateChecksum(
            $token->token_value,
            $token->data_hash
        );

        return hash_equals($expectedChecksum, $token->checksum);
    }

    private function formatTokenResponse(Token $token, bool $isNew): array
    {
        return [
            'token_value' => $token->token_value,
            'format_preserved_token' => $token->format_preserved_token,
            'token_type' => $token->token_type,
            'vault_id' => $token->vault_id,
            'created_at' => $token->created_at,
            'expires_at' => $token->expires_at,
            'is_new' => $isNew,
        ];
    }

    private function logTokenizationEvent(
        ?Vault $vault,
        ?Token $token,
        string $operation,
        string $result,
        float $startTime,
        ?string $errorMessage = null,
        array $metadata = []
    ): void {
        $this->auditService->logEvent([
            'vault_id' => $vault?->id,
            'token_id' => $token?->id,
            'operation' => $operation,
            'result' => $result,
            'error_message' => $errorMessage,
            'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000),
            'response_metadata' => $metadata,
            'risk_level' => in_array($operation, ['detokenize', 'bulk_detokenize']) ? 'high' : 'medium',
        ]);
    }
}
