<?php

namespace App\Application\Commands;

class RevokeTokenCommand
{
    public function __construct(
        public readonly string $tokenId,
        public readonly ?string $reason = null
    ) {}
}