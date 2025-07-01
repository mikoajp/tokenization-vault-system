<?php

namespace App\Domain\Vault\Exceptions;

class VaultNotFoundException extends VaultException
{
    public function __construct(string $vaultId, array $context = [])
    {
        parent::__construct(
            "Vault with ID {$vaultId} not found", 
            'VAULT_NOT_FOUND', 
            array_merge($context, ['vault_id' => $vaultId])
        );
    }
}