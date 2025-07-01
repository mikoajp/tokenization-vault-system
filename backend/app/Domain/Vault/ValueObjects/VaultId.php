<?php

namespace App\Domain\Vault\ValueObjects;

use App\Shared\ValueObjects\UuidValueObject;

final class VaultId extends UuidValueObject
{
    public static function generate(): self
    {
        return new self(\Illuminate\Support\Str::uuid()->toString());
    }
}