<?php

namespace App\Domain\Tokenization\ValueObjects;

use InvalidArgumentException;

final class TokenType
{
    private const RANDOM = 'random';
    private const FORMAT_PRESERVING = 'format_preserving';
    private const SEQUENTIAL = 'sequential';

    private const VALID_TYPES = [
        self::RANDOM,
        self::FORMAT_PRESERVING,
        self::SEQUENTIAL,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    public static function random(): self
    {
        return new self(self::RANDOM);
    }

    public static function formatPreserving(): self
    {
        return new self(self::FORMAT_PRESERVING);
    }

    public static function sequential(): self
    {
        return new self(self::SEQUENTIAL);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isRandom(): bool
    {
        return $this->value === self::RANDOM;
    }

    public function isFormatPreserving(): bool
    {
        return $this->value === self::FORMAT_PRESERVING;
    }

    public function isSequential(): bool
    {
        return $this->value === self::SEQUENTIAL;
    }

    public function preservesFormat(): bool
    {
        return $this->isFormatPreserving();
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
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid token type: {$value}. Valid types are: " . implode(', ', self::VALID_TYPES)
            );
        }
    }
}