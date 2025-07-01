<?php

namespace App\Domain\Tokenization\ValueObjects;

use InvalidArgumentException;

final class TokenStatus
{
    private const ACTIVE = 'active';
    private const REVOKED = 'revoked';
    private const EXPIRED = 'expired';
    private const COMPROMISED = 'compromised';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::REVOKED,
        self::EXPIRED,
        self::COMPROMISED,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function revoked(): self
    {
        return new self(self::REVOKED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function compromised(): self
    {
        return new self(self::COMPROMISED);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isRevoked(): bool
    {
        return $this->value === self::REVOKED;
    }

    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    public function isCompromised(): bool
    {
        return $this->value === self::COMPROMISED;
    }

    public function isUsable(): bool
    {
        return $this->isActive();
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
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid token status: {$value}. Valid statuses are: " . implode(', ', self::VALID_STATUSES)
            );
        }
    }
}