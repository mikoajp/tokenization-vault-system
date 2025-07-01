<?php

namespace App\Domain\Tokenization\ValueObjects;

use InvalidArgumentException;

final class TokenValue
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLength(): int
    {
        return strlen($this->value);
    }

    public function startsWith(string $prefix): bool
    {
        return str_starts_with($this->value, $prefix);
    }

    public function endsWith(string $suffix): bool
    {
        return str_ends_with($this->value, $suffix);
    }

    public function matches(string $pattern): bool
    {
        return preg_match($pattern, $this->value) === 1;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Token value cannot be empty');
        }

        if (strlen($value) < 8) {
            throw new InvalidArgumentException('Token value must be at least 8 characters long');
        }

        if (strlen($value) > 128) {
            throw new InvalidArgumentException('Token value cannot exceed 128 characters');
        }

        // Check for basic security patterns
        if (preg_match('/^[0]+$/', $value)) {
            throw new InvalidArgumentException('Token value cannot be all zeros');
        }

        if (preg_match('/^[1]+$/', $value)) {
            throw new InvalidArgumentException('Token value cannot be all ones');
        }
    }
}