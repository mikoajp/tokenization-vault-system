<?php

namespace App\Shared\Traits;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Support\Str;

trait AuditableTrait
{
    protected static function bootAuditableTrait()
    {
        static::created(function ($model) {
            $model->logAuditEvent('create', 'success');
        });

        static::updated(function ($model) {
            $model->logAuditEvent('update', 'success');
        });

        static::deleted(function ($model) {
            $model->logAuditEvent('delete', 'success');
        });
    }

    /**
     * Log an audit event for this model
     */
    public function logAuditEvent(string $operation, string $result, array $metadata = []): AuditLog
    {
        $requestId = request()->header('X-Request-ID') ?? Str::uuid();

        return AuditLog::create([
            'vault_id' => $this->getVaultId(),
            'token_id' => $this->getTokenId(),
            'operation' => $this->getAuditOperation($operation),
            'result' => $result,
            'user_id' => $this->getCurrentUserId(),
            'api_key_id' => $this->getCurrentApiKeyId(),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => $requestId,
            'request_metadata' => $this->getSanitizedRequestData(),
            'response_metadata' => $metadata,
            'risk_level' => $this->calculateRiskLevel($operation),
            'pci_relevant' => $this->isPciRelevant(),
        ]);
    }

    /**
     * Get vault ID for audit log
     */
    protected function getVaultId(): ?string
    {
        if ($this instanceof \App\Domain\Vault\Models\Vault) {
            return $this->id;
        }

        if ($this instanceof \App\Domain\Tokenization\Models\Token) {
            return $this->vault_id;
        }

        if (isset($this->vault_id)) {
            return $this->vault_id;
        }

        return null;
    }

    /**
     * Get token ID for audit log
     */
    protected function getTokenId(): ?string
    {
        if ($this instanceof \App\Domain\Tokenization\Models\Token) {
            return $this->id;
        }

        return null;
    }

    /**
     * Get current user ID from authentication
     */
    protected function getCurrentUserId(): ?string
    {
        if (auth()->check()) {
            return auth()->id();
        }

        return request()->header('X-User-ID');
    }

    /**
     * Get current API key ID from request
     */
    protected function getCurrentApiKeyId(): ?string
    {
        return request()->header('X-API-Key-ID');
    }

    /**
     * Get sanitized request data (remove sensitive information)
     */
    protected function getSanitizedRequestData(): array
    {
        $data = request()->all();

        $sensitiveFields = [
            'password', 'token', 'api_key', 'secret', 'key',
            'ssn', 'card_number', 'cvv', 'pin', 'account_number'
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        foreach ($data as $key => $value) {
            if (is_string($value) && $this->containsSensitiveData($value)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Check if string contains sensitive data patterns
     */
    protected function containsSensitiveData(string $value): bool
    {
        // Credit card pattern
        if (preg_match('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', $value)) {
            return true;
        }

        // SSN pattern
        if (preg_match('/\b\d{3}-?\d{2}-?\d{4}\b/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Get audit operation name
     */
    protected function getAuditOperation(string $operation): string
    {
        $modelName = strtolower(class_basename($this));
        return $modelName . '_' . $operation;
    }

    /**
     * Calculate risk level for operation
     */
    protected function calculateRiskLevel(string $operation): string
    {
        $highRiskOperations = ['delete', 'detokenize', 'key_rotation'];
        $mediumRiskOperations = ['update', 'revoke', 'expire'];

        if (in_array($operation, $highRiskOperations)) {
            return 'high';
        }

        if (in_array($operation, $mediumRiskOperations)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check if operation is PCI relevant
     */
    protected function isPciRelevant(): bool
    {
        $pciModels = [
            \App\Domain\Vault\Models\Vault::class,
            \App\Domain\Tokenization\Models\Token::class,
            \App\Domain\Vault\Models\VaultKey::class,
        ];

        return in_array(get_class($this), $pciModels);
    }
}