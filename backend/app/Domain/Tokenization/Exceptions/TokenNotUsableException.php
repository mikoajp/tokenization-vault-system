<?php

namespace App\Domain\Tokenization\Exceptions;

class TokenNotUsableException extends TokenizationException
{
    public function __construct(string $message = 'Token is not usable', array $context = [])
    {
        parent::__construct($message, 'TOKEN_NOT_USABLE', $context);
    }
}