<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\VaultKey;
use App\Models\DataRetentionPolicy;
use App\Exceptions\VaultException;
use Illuminate\Support\Facades\DB;

class VaultManager
{
    public function __construct(
        private EncryptionService $encryptionService,
        private AuditService $auditService
    ) {}

    /**
     * Create a new vault
     */
    public function createVault(array $vaultData): Vault
    {
        return DB::transaction(function () use ($vaultData) {
            try {
                // Create vault
                $vault = Vault::create(array_merge($vaultData, [
                    'encryption_algorithm' => 'AES-256-GCM',
                    'encryption_key_reference' => 'hsm_key_' . \Illuminate\Support\Str::uuid(),
                    'current_token_count' => 0,
                    'status' => 'active',
                ]));

                // Generate initial encryption key
                $this->rotateVaultKey($vault->id);

                // Create default retention policy
                $this->createDefaultRetentionPolicy($vault);

                // Log vault creation
                $this->auditService->logEvent([
                    'vault_id' => $vault->id,
                    'operation' => 'vault_create',
                    'result' => 'success',
                    'response_metadata' => [
                        'vault_name' => $vault->name,
                        'data_type' => $vault->data_type,
                    ],
                ]);

                return $vault;

            } catch (\Exception $e) {
                $this->auditService->logEvent([
                    'operation' => 'vault_create',
                    'result' => 'failure',
                    'error_message' => $e->getMessage(),
                ]);

                throw new VaultException('Vault creation failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Update vault configuration
     */
    public function updateVault(string $vaultId, array $updateData): Vault
    {
        try {
            $vault = Vault::findOrFail($vaultId);

            // Store original data for audit
            $originalData = $vault->only(['name', 'description', 'status', 'max_tokens']);

            $vault->update($updateData);

            // Log vault update
            $this->auditService->logEvent([
                'vault_id' => $vault->id,
                'operation' => 'vault_update',
                'result' => 'success',
                'response_metadata' => [
                    'original' => $originalData,
                    'updated' => $updateData,
                ],
            ]);

            return $vault;

        } catch (\Exception $e) {
            $this->auditService->logEvent([
                'vault_id' => $vaultId,
                'operation' => 'vault_update',
                'result' => 'failure',
                'error_message' => $e->getMessage(),
            ]);

            throw new VaultException('Vault update failed: ' . $e->getMessage());
        }
    }

    /**
     * Rotate vault encryption key
     */
    public function rotateVaultKey(string $vaultId): VaultKey
    {
        return DB::transaction(function () use ($vaultId) {
            try {
                $vault = Vault::findOrFail($vaultId);

                // Retire current active key
                $currentKey = $vault->getActiveKey();
                if ($currentKey) {
                    $currentKey->retire();
                }

                // Generate new key version
                $newVersion = 'v' . (((int) str_replace('v', '', $currentKey?->key_version ?? 'v0')) + 1);

                // Generate new encryption key
                $keyData = $this->encryptionService->generateSecureToken(32);
                $encryptedKey = encrypt($keyData); // Encrypt with master key
                $keyHash = hash('sha256', $keyData);

                // Create new key record
                $newKey = VaultKey::create([
                    'vault_id' => $vault->id,
                    'key_version' => $newVersion,
                    'encrypted_key' => $encryptedKey,
                    'key_hash' => $keyHash,
                    'status' => 'active',
                    'activated_at' => now(),
                ]);

                // Update vault last rotation timestamp
                $vault->update(['last_key_rotation' => now()]);

                // Log key rotation
                $this->auditService->logEvent([
                    'vault_id' => $vault->id,
                    'operation' => 'key_rotation',
                    'result' => 'success',
                    'response_metadata' => [
                        'old_version' => $currentKey?->key_version,
                        'new_version' => $newVersion,
                    ],
                    'risk_level' => 'high',
                ]);

                return $newKey;

            } catch (\Exception $e) {
                $this->auditService->logEvent([
                    'vault_id' => $vaultId,
                    'operation' => 'key_rotation',
                    'result' => 'failure',
                    'error_message' => $e->getMessage(),
                    'risk_level' => 'critical',
                ]);

                throw new VaultException('Key rotation failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Get vault statistics
     */
    public function getVaultStatistics(string $vaultId): array
    {
        $vault = Vault::with(['tokens', 'auditLogs'])->findOrFail($vaultId);

        $tokenStats = $vault->tokens()
            ->selectRaw('
                COUNT(*) as total_tokens,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active_tokens,
                COUNT(CASE WHEN status = "expired" THEN 1 END) as expired_tokens,
                COUNT(CASE WHEN status = "revoked" THEN 1 END) as revoked_tokens,
                AVG(usage_count) as avg_usage_count,
                MAX(usage_count) as max_usage_count
            ')
            ->first();

        $auditStats = $vault->auditLogs()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('
                COUNT(*) as total_operations,
                COUNT(CASE WHEN result = "success" THEN 1 END) as successful_operations,
                COUNT(CASE WHEN result = "failure" THEN 1 END) as failed_operations,
                COUNT(CASE WHEN operation = "tokenize" THEN 1 END) as tokenize_operations,
                COUNT(CASE WHEN operation = "detokenize" THEN 1 END) as detokenize_operations
            ')
            ->first();

        return [
            'vault_info' => [
                'id' => $vault->id,
                'name' => $vault->name,
                'data_type' => $vault->data_type,
                'status' => $vault->status,
                'capacity_used' => $vault->current_token_count,
                'capacity_total' => $vault->max_tokens,
                'capacity_percentage' => round(($vault->current_token_count / $vault->max_tokens) * 100, 2),
            ],
            'token_statistics' => $tokenStats->toArray(),
            'operation_statistics' => $auditStats->toArray(),
            'security_info' => [
                'last_key_rotation' => $vault->last_key_rotation,
                'needs_key_rotation' => $vault->needsKeyRotation(),
                'encryption_algorithm' => $vault->encryption_algorithm,
                'active_key_version' => $vault->getActiveKey()?->key_version,
            ],
        ];
    }

    /**
     * Create default retention policy for vault
     */
    private function createDefaultRetentionPolicy(Vault $vault): DataRetentionPolicy
    {
        return DataRetentionPolicy::create([
            'vault_id' => $vault->id,
            'policy_name' => 'Default Retention Policy',
            'retention_days' => $vault->retention_days,
            'action_after_retention' => 'delete',
            'auto_execute' => true,
            'cron_schedule' => '0 2 * * *', // Daily at 2 AM
            'status' => 'active',
            'next_execution_at' => now()->addDay(),
        ]);
    }
}
