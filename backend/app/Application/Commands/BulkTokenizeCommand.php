<?php

namespace App\Application\Commands;

class BulkTokenizeCommand
{
    public function __construct(
        public readonly string $vaultId,
        public readonly array $dataItems,
        public readonly string $tokenType = 'random',
        public readonly array $commonMetadata = []
    ) {}
}