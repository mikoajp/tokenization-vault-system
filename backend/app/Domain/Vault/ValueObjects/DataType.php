<?php

namespace App\Domain\Vault\ValueObjects;

use InvalidArgumentException;

final class DataType
{
    private const CARD = 'card';
    private const SSN = 'ssn';
    private const BANK_ACCOUNT = 'bank_account';
    private const CUSTOM = 'custom';

    private const VALID_TYPES = [
        self::CARD,
        self::SSN,
        self::BANK_ACCOUNT,
        self::CUSTOM,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    public static function card(): self
    {
        return new self(self::CARD);
    }

    public static function ssn(): self
    {
        return new self(self::SSN);
    }

    public static function bankAccount(): self
    {
        return new self(self::BANK_ACCOUNT);
    }

    public static function custom(): self
    {
        return new self(self::CUSTOM);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isCard(): bool
    {
        return $this->value === self::CARD;
    }

    public function isSsn(): bool
    {
        return $this->value === self::SSN;
    }

    public function isBankAccount(): bool
    {
        return $this->value === self::BANK_ACCOUNT;
    }

    public function isCustom(): bool
    {
        return $this->value === self::CUSTOM;
    }

    public function requiresSpecialValidation(): bool
    {
        return in_array($this->value, [self::CARD, self::SSN, self::BANK_ACCOUNT]);
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
                "Invalid data type: {$value}. Valid types are: " . implode(', ', self::VALID_TYPES)
            );
        }
    }
}