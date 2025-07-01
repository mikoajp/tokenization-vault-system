<?php

namespace App\Application\Commands;

class CreateVaultCommand
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $dataType,
        public readonly string $encryptionAlgorithm,
        public readonly array $allowedOperations,
        public readonly ?array $accessRestrictions,
        public readonly int $maxTokens,
        public readonly int $retentionDays,
        public readonly int $keyRotationIntervalDays
    ) {}
}