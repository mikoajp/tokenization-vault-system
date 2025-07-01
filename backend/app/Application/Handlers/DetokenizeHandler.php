<?php

namespace App\Application\Handlers;

use App\Application\Commands\DetokenizeCommand;
use App\Domain\Tokenization\Services\TokenizationDomainService;
use App\Domain\Tokenization\ValueObjects\TokenValue;

class DetokenizeHandler
{
    public function __construct(
        private TokenizationDomainService $tokenizationDomainService
    ) {}

    public function handle(DetokenizeCommand $command): string
    {
        $tokenValue = new TokenValue($command->tokenValue);

        return $this->tokenizationDomainService->detokenize($tokenValue);
    }
}