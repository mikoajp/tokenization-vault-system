<?php

namespace App\Domain\Audit\Events;

use App\Domain\Audit\Models\AuditLog;

class AuditLogCreated
{
    public function __construct(
        public readonly AuditLog $auditLog
    ) {}
}