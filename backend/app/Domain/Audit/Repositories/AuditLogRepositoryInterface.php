<?php

namespace App\Domain\Audit\Repositories;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\ValueObjects\AuditLogId;
use App\Domain\Audit\ValueObjects\OperationType;
use App\Domain\Audit\ValueObjects\RiskLevel;

interface AuditLogRepositoryInterface
{
    public function save(AuditLog $auditLog): void;
    
    public function findById(AuditLogId $id): ?AuditLog;
    
    public function findByVaultId(string $vaultId, int $limit = 100): array;
    
    public function findByOperation(OperationType $operation, int $limit = 100): array;
    
    public function findByRiskLevel(RiskLevel $riskLevel, int $limit = 100): array;
    
    public function findByIpAddress(string $ipAddress, \DateTimeInterface $since): array;
    
    public function findFailedOperations(\DateTimeInterface $since): array;
    
    public function findPciRelevantLogs(\DateTimeInterface $from, \DateTimeInterface $to): array;
    
    public function findHighRiskOperations(\DateTimeInterface $since): array;
    
    public function countOperationsByType(\DateTimeInterface $from, \DateTimeInterface $to): array;
    
    public function getAverageProcessingTime(\DateTimeInterface $from, \DateTimeInterface $to): float;
    
    public function findOldLogsForArchival(\DateTimeInterface $before): array;
    
    public function markAsArchived(array $auditLogIds, string $archiveLocation): void;
    
    public function deleteArchivedLogs(\DateTimeInterface $before): int;
}