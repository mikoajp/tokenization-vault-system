<?php

namespace App\Application\Commands;

class TokenizeCommand
{
    public function __construct(
        public readonly string $vaultId,
        public readonly string $data,
        public readonly string $tokenType = 'random',
        public readonly array $metadata = [],
        public readonly ?string $expiresAt = null
    ) {}
}