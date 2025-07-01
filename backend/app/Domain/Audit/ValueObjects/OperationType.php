<?php

namespace App\Domain\Audit\ValueObjects;

class OperationType
{
    private string $value;

    private const VALID_OPERATIONS = [
        'tokenize',
        'detokenize',
        'bulk_tokenize',
        'bulk_detokenize',
        'vault_create',
        'vault_update',
        'vault_delete',
        'vault_key_rotation',
        'token_revoke',
        'export_tokens',
        'unauthorized_access',
        'login',
        'logout',
        'api_key_create',
        'api_key_revoke',
    ];

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_OPERATIONS)) {
            throw new \InvalidArgumentException("Invalid operation type: {$value}");
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isTokenization(): bool
    {
        return in_array($this->value, ['tokenize', 'bulk_tokenize']);
    }

    public function isDetokenization(): bool
    {
        return in_array($this->value, ['detokenize', 'bulk_detokenize']);
    }

    public function isVaultOperation(): bool
    {
        return str_starts_with($this->value, 'vault_');
    }

    public function isHighRiskOperation(): bool
    {
        return in_array($this->value, [
            'detokenize',
            'bulk_detokenize',
            'export_tokens',
            'vault_delete',
            'vault_key_rotation'
        ]);
    }

    public function isUnauthorizedAccess(): bool
    {
        return $this->value === 'unauthorized_access';
    }

    public function equals(OperationType $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function tokenize(): self
    {
        return new self('tokenize');
    }

    public static function detokenize(): self
    {
        return new self('detokenize');
    }

    public static function bulkTokenize(): self
    {
        return new self('bulk_tokenize');
    }

    public static function bulkDetokenize(): self
    {
        return new self('bulk_detokenize');
    }

    public static function vaultCreate(): self
    {
        return new self('vault_create');
    }

    public static function vaultUpdate(): self
    {
        return new self('vault_update');
    }

    public static function vaultDelete(): self
    {
        return new self('vault_delete');
    }

    public static function vaultKeyRotation(): self
    {
        return new self('vault_key_rotation');
    }

    public static function unauthorizedAccess(): self
    {
        return new self('unauthorized_access');
    }
}