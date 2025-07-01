<?php

namespace App\Domain\Tokenization\Exceptions;

class TokenNotActiveException extends TokenizationException
{
    public function __construct(string $message = 'Token is not active', array $context = [])
    {
        parent::__construct($message, 'TOKEN_NOT_ACTIVE', $context);
    }
}