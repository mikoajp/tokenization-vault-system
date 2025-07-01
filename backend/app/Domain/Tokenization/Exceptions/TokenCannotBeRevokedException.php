<?php

namespace App\Domain\Tokenization\Exceptions;

class TokenCannotBeRevokedException extends TokenizationException
{
    public function __construct(string $message = 'Token cannot be revoked', array $context = [])
    {
        parent::__construct($message, 'TOKEN_CANNOT_BE_REVOKED', $context);
    }
}