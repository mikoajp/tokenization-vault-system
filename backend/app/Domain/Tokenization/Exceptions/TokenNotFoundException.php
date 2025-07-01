<?php

namespace App\Domain\Tokenization\Exceptions;

class TokenNotFoundException extends TokenizationException
{
    public function __construct(string $tokenValue, array $context = [])
    {
        parent::__construct(
            "Token with value {$tokenValue} not found", 
            'TOKEN_NOT_FOUND', 
            array_merge($context, ['token_value' => $tokenValue])
        );
    }
}