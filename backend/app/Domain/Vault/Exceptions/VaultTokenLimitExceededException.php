<?php

namespace App\Domain\Vault\Exceptions;

class VaultTokenLimitExceededException extends VaultException
{
    public function __construct(string $message = 'Vault token limit exceeded', array $context = [])
    {
        parent::__construct($message, 'VAULT_TOKEN_LIMIT_EXCEEDED', $context);
    }
}