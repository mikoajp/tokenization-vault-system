<?php

namespace App\Application\Commands;

class DetokenizeCommand
{
    public function __construct(
        public readonly string $tokenValue
    ) {}
}