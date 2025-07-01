<?php

namespace App\Domain\Audit\Events;

use App\Domain\Audit\Models\AuditLog;

class SecurityPatternDetected
{
    public function __construct(
        public readonly AuditLog $auditLog,
        public readonly string $patternType,
        public readonly array $metadata = []
    ) {}
}