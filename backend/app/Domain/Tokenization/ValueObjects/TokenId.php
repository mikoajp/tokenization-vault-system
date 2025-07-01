<?php

namespace App\Domain\Tokenization\ValueObjects;

use App\Shared\ValueObjects\UuidValueObject;

final class TokenId extends UuidValueObject
{
    public static function generate(): self
    {
        return new self(\Illuminate\Support\Str::uuid()->toString());
    }
}