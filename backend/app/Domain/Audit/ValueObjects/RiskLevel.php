<?php

namespace App\Domain\Audit\ValueObjects;

class RiskLevel
{
    private string $value;

    private const VALID_LEVELS = ['low', 'medium', 'high', 'critical'];

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_LEVELS)) {
            throw new \InvalidArgumentException("Invalid risk level: {$value}");
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isLow(): bool
    {
        return $this->value === 'low';
    }

    public function isMedium(): bool
    {
        return $this->value === 'medium';
    }

    public function isHigh(): bool
    {
        return $this->value === 'high';
    }

    public function isCritical(): bool
    {
        return $this->value === 'critical';
    }

    public function isHighOrCritical(): bool
    {
        return $this->isHigh() || $this->isCritical();
    }

    public function getNumericValue(): int
    {
        return match($this->value) {
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4,
        };
    }

    public function isHigherThan(RiskLevel $other): bool
    {
        return $this->getNumericValue() > $other->getNumericValue();
    }

    public function equals(RiskLevel $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function low(): self
    {
        return new self('low');
    }

    public static function medium(): self
    {
        return new self('medium');
    }

    public static function high(): self
    {
        return new self('high');
    }

    public static function critical(): self
    {
        return new self('critical');
    }
}