<?php

namespace App\Shared\Exceptions;

class EncryptionException extends DomainException
{
    public function __construct(string $message = 'Encryption operation failed', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, 'ENCRYPTION_ERROR', [], $code, $previous);
    }
}