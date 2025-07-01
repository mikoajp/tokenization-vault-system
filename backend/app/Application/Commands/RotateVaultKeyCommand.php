<?php

namespace App\Application\Commands;

class RotateVaultKeyCommand
{
    public function __construct(
        public readonly string $vaultId
    ) {}
}