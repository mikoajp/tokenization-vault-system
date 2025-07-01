<?php

namespace App\Domain\Vault\ValueObjects;

use InvalidArgumentException;

final class EncryptionConfig
{
    private const SUPPORTED_ALGORITHMS = [
        'AES-256-GCM',
        'AES-256-CBC',
        'ChaCha20-Poly1305',
    ];

    private string $algorithm;
    private string $keyReference;

    public function __construct(string $algorithm, string $keyReference)
    {
        $this->validateAlgorithm($algorithm);
        $this->validateKeyReference($keyReference);
        
        $this->algorithm = $algorithm;
        $this->keyReference = $keyReference;
    }

    public static function default(): self
    {
        return new self('AES-256-GCM', '');
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getKeyReference(): string
    {
        return $this->keyReference;
    }

    public function isAesGcm(): bool
    {
        return $this->algorithm === 'AES-256-GCM';
    }

    public function isAesCbc(): bool
    {
        return $this->algorithm === 'AES-256-CBC';
    }

    public function isChaCha20(): bool
    {
        return $this->algorithm === 'ChaCha20-Poly1305';
    }

    public function supportsAuthenticatedEncryption(): bool
    {
        return in_array($this->algorithm, ['AES-256-GCM', 'ChaCha20-Poly1305']);
    }

    public function equals(self $other): bool
    {
        return $this->algorithm === $other->algorithm 
            && $this->keyReference === $other->keyReference;
    }

    public function withNewKeyReference(string $keyReference): self
    {
        return new self($this->algorithm, $keyReference);
    }

    private function validateAlgorithm(string $algorithm): void
    {
        if (!in_array($algorithm, self::SUPPORTED_ALGORITHMS, true)) {
            throw new InvalidArgumentException(
                "Unsupported encryption algorithm: {$algorithm}. Supported algorithms are: " 
                . implode(', ', self::SUPPORTED_ALGORITHMS)
            );
        }
    }

    private function validateKeyReference(string $keyReference): void
    {
        if (empty($keyReference)) {
            return; // Allow empty for new vaults
        }

        if (strlen($keyReference) < 10) {
            throw new InvalidArgumentException('Key reference must be at least 10 characters long');
        }
    }
}