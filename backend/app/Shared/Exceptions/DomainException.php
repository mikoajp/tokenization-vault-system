<?php

namespace App\Shared\Exceptions;

use Exception;

abstract class DomainException extends Exception
{
    protected string $errorCode;
    protected array $context = [];

    public function __construct(string $message = '', string $errorCode = '', array $context = [], int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        
        $this->errorCode = $errorCode ?: static::class;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
}