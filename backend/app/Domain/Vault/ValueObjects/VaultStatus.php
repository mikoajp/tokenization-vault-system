<?php

namespace App\Domain\Vault\ValueObjects;

use InvalidArgumentException;

final class VaultStatus
{
    private const ACTIVE = 'active';
    private const INACTIVE = 'inactive';
    private const ARCHIVED = 'archived';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::INACTIVE,
        self::ARCHIVED,
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

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->value === self::INACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->value === self::ARCHIVED;
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
                "Invalid vault status: {$value}. Valid statuses are: " . implode(', ', self::VALID_STATUSES)
            );
        }
    }
}