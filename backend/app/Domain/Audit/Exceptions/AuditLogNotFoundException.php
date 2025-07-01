<?php

namespace App\Domain\Audit\Exceptions;

class AuditLogNotFoundException extends AuditException
{
    public function __construct(string $auditLogId)
    {
        parent::__construct("Audit log not found: {$auditLogId}");
    }
}