<?php

namespace App\Domain\Audit\Services;

use App\Jobs\ProcessAuditLog;
use App\Infrastructure\Persistence\Eloquent\Models\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Models\SecurityAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class AuditDomainService
{
    /**
     * Log audit event asynchronously using RabbitMQ
     */
    public function logEvent(array $data): string
    {
        try {
            $auditId = \Illuminate\Support\Str::uuid();

            $auditData = $this->prepareAuditData($data, $auditId);

            Queue::pushOn(
                $this->determineQueue($auditData),
                new ProcessAuditLog($auditData),
                [
                    'priority' => $this->getPriority($auditData),
                    'delay' => 0, // Process immediately
                ]
            );

            return $auditId;

        } catch (\Exception $e) {
            Log::error('Failed to queue audit log', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Process audit log entry (called by queue worker)
     */
    public function processAuditLog(array $auditData): void
    {
        try {
            $auditLog = AuditLog::create($auditData);

            // Check for security patterns
            $this->analyzeSecurityPatterns($auditLog);

            // Update metrics
            $this->updateMetrics($auditLog);

            // Archive old logs if needed
            $this->checkArchivalNeeds();

        } catch (\Exception $e) {
            Log::error('Failed to process audit log', [
                'error' => $e->getMessage(),
                'audit_data' => $auditData
            ]);
            throw $e;
        }
    }

    /**
     * Analyze security patterns and create alerts if needed
     */
    private function analyzeSecurityPatterns(AuditLog $auditLog): void
    {
        // Check for failed operations
        if ($auditLog->result === 'error') {
            $this->checkFailedOperationPattern($auditLog);
        }

        // Check for suspicious IP activity
        if ($auditLog->ip_address) {
            $this->checkSuspiciousIpActivity($auditLog);
        }

        // Check for unusual operation patterns
        $this->checkUnusualOperationPatterns($auditLog);

        // Check for high-risk operations
        if ($auditLog->risk_level === 'high') {
            $this->createHighRiskAlert($auditLog);
        }
    }

    /**
     * Check for patterns of failed operations
     */
    private function checkFailedOperationPattern(AuditLog $auditLog): void
    {
        $recentFailures = AuditLog::where('ip_address', $auditLog->ip_address)
            ->where('result', 'error')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        if ($recentFailures >= 5) {
            $this->createOrUpdateSecurityAlert([
                'type' => 'repeated_failures',
                'severity' => 'high',
                'ip_address' => $auditLog->ip_address,
                'vault_id' => $auditLog->vault_id,
                'message' => "Multiple failed operations from IP {$auditLog->ip_address}",
                'metadata' => [
                    'failure_count' => $recentFailures,
                    'time_window' => '15 minutes',
                    'triggering_operation' => $auditLog->operation
                ],
                'triggering_audit_log_id' => $auditLog->id
            ]);
        }
    }

    /**
     * Check for suspicious IP activity
     */
    private function checkSuspiciousIpActivity(AuditLog $auditLog): void
    {
        // Check for operations from new IP addresses
        $isNewIp = !AuditLog::where('ip_address', $auditLog->ip_address)
            ->where('created_at', '<', now()->subDays(7))
            ->exists();

        if ($isNewIp && $auditLog->operation === 'tokenize') {
            $this->createOrUpdateSecurityAlert([
                'type' => 'new_ip_tokenization',
                'severity' => 'medium',
                'ip_address' => $auditLog->ip_address,
                'vault_id' => $auditLog->vault_id,
                'message' => "Tokenization from new IP address {$auditLog->ip_address}",
                'metadata' => [
                    'first_seen' => now()->toISOString(),
                    'operation' => $auditLog->operation
                ],
                'triggering_audit_log_id' => $auditLog->id
            ]);
        }

        // Check for high volume from single IP
        $recentOperations = AuditLog::where('ip_address', $auditLog->ip_address)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentOperations >= 100) {
            $this->createOrUpdateSecurityAlert([
                'type' => 'high_volume_ip',
                'severity' => 'high',
                'ip_address' => $auditLog->ip_address,
                'message' => "High volume of operations from IP {$auditLog->ip_address}",
                'metadata' => [
                    'operation_count' => $recentOperations,
                    'time_window' => '1 hour'
                ],
                'triggering_audit_log_id' => $auditLog->id
            ]);
        }
    }

    /**
     * Check for unusual operation patterns
     */
    private function checkUnusualOperationPatterns(AuditLog $auditLog): void
    {
        // Check for operations outside business hours
        $hour = now()->hour;
        if ($hour < 6 || $hour > 22) {
            $this->createOrUpdateSecurityAlert([
                'type' => 'off_hours_operation',
                'severity' => 'low',
                'user_id' => $auditLog->user_id,
                'vault_id' => $auditLog->vault_id,
                'message' => "Operation performed outside business hours",
                'metadata' => [
                    'operation' => $auditLog->operation,
                    'hour' => $hour
                ],
                'triggering_audit_log_id' => $auditLog->id
            ]);
        }

        // Check for bulk operations
        if ($auditLog->operation === 'bulk_tokenize') {
            $requestMetadata = $auditLog->request_metadata ?? [];
            $itemCount = $requestMetadata['item_count'] ?? 0;

            if ($itemCount > 1000) {
                $this->createOrUpdateSecurityAlert([
                    'type' => 'large_bulk_operation',
                    'severity' => 'medium',
                    'user_id' => $auditLog->user_id,
                    'vault_id' => $auditLog->vault_id,
                    'message' => "Large bulk tokenization operation ({$itemCount} items)",
                    'metadata' => [
                        'item_count' => $itemCount,
                        'operation' => $auditLog->operation
                    ],
                    'triggering_audit_log_id' => $auditLog->id
                ]);
            }
        }
    }

    /**
     * Create alert for high-risk operations
     */
    private function createHighRiskAlert(AuditLog $auditLog): void
    {
        $this->createOrUpdateSecurityAlert([
            'type' => 'high_risk_operation',
            'severity' => 'high',
            'user_id' => $auditLog->user_id,
            'vault_id' => $auditLog->vault_id,
            'ip_address' => $auditLog->ip_address,
            'message' => "High-risk operation detected: {$auditLog->operation}",
            'metadata' => [
                'operation' => $auditLog->operation,
                'risk_level' => $auditLog->risk_level,
                'error_message' => $auditLog->error_message
            ],
            'triggering_audit_log_id' => $auditLog->id
        ]);
    }

    /**
     * Create or update security alert
     */
    private function createOrUpdateSecurityAlert(array $alertData): void
    {
        // Try to find existing similar alert from last 24 hours
        $existingAlert = SecurityAlert::where('type', $alertData['type'])
            ->where('ip_address', $alertData['ip_address'] ?? null)
            ->where('user_id', $alertData['user_id'] ?? null)
            ->where('vault_id', $alertData['vault_id'] ?? null)
            ->where('status', '!=', 'resolved')
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($existingAlert) {
            // Update existing alert
            $existingAlert->incrementCount();
            $existingAlert->updateMetadata($alertData['metadata'] ?? []);
        } else {
            // Create new alert
            SecurityAlert::create([
                ...$alertData,
                'status' => 'open',
                'count' => 1,
                'first_occurrence' => now(),
                'last_occurrence' => now(),
            ]);
        }
    }

    /**
     * Update metrics cache
     */
    private function updateMetrics(AuditLog $auditLog): void
    {
        $cacheKey = "audit_metrics:" . now()->format('Y-m-d-H');
        
        Cache::remember($cacheKey, 3600, function () {
            return [
                'total_operations' => 0,
                'successful_operations' => 0,
                'failed_operations' => 0,
                'high_risk_operations' => 0,
            ];
        });

        Cache::increment($cacheKey . ':total_operations');
        
        if ($auditLog->result === 'success') {
            Cache::increment($cacheKey . ':successful_operations');
        } elseif ($auditLog->result === 'error') {
            Cache::increment($cacheKey . ':failed_operations');
        }

        if ($auditLog->risk_level === 'high') {
            Cache::increment($cacheKey . ':high_risk_operations');
        }
    }

    /**
     * Check if old logs need archival
     */
    private function checkArchivalNeeds(): void
    {
        $oldLogsCount = AuditLog::where('created_at', '<', now()->subDays(90))
            ->whereNull('archived_at')
            ->count();

        if ($oldLogsCount > 10000) {
            // Trigger archival job
            \App\Jobs\ArchiveAuditLogs::dispatch();
        }
    }

    /**
     * Prepare audit data for storage
     */
    private function prepareAuditData(array $data, string $auditId): array
    {
        return [
            'id' => $auditId,
            'vault_id' => $data['vault_id'] ?? null,
            'token_id' => $data['token_id'] ?? null,
            'operation' => $data['operation'],
            'result' => $data['result'] ?? 'success',
            'error_message' => $data['error_message'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'api_key_id' => $data['api_key_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'request_id' => $data['request_id'] ?? request()->header('X-Request-ID'),
            'request_metadata' => $data['request_metadata'] ?? [],
            'response_metadata' => $data['response_metadata'] ?? [],
            'processing_time_ms' => $data['processing_time_ms'] ?? 0,
            'risk_level' => $this->calculateRiskLevel($data),
            'pci_relevant' => $this->isPciRelevant($data),
            'compliance_reference' => $this->generateComplianceReference($auditId),
        ];
    }

    /**
     * Calculate risk level for operation
     */
    private function calculateRiskLevel(array $data): string
    {
        $operation = $data['operation'];
        $result = $data['result'] ?? 'success';

        // High risk operations
        if (in_array($operation, ['detokenize', 'bulk_detokenize', 'export_tokens'])) {
            return 'high';
        }

        // Failed operations are higher risk
        if ($result === 'error') {
            return 'medium';
        }

        // Check for suspicious patterns
        if (isset($data['ip_address'])) {
            $recentFailures = AuditLog::where('ip_address', $data['ip_address'])
                ->where('result', 'error')
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentFailures > 3) {
                return 'high';
            }
        }

        return 'low';
    }

    /**
     * Check if operation is PCI relevant
     */
    private function isPciRelevant(array $data): bool
    {
        $pciOperations = [
            'tokenize', 'detokenize', 'bulk_tokenize', 'bulk_detokenize',
            'export_tokens', 'vault_key_rotation'
        ];

        return in_array($data['operation'], $pciOperations);
    }

    /**
     * Generate compliance reference
     */
    private function generateComplianceReference(string $auditId): string
    {
        return sprintf(
            'AUDIT-%s-%s',
            now()->format('Ymd'),
            strtoupper(substr($auditId, 0, 8))
        );
    }

    /**
     * Determine appropriate queue for audit log
     */
    private function determineQueue(array $auditData): string
    {
        if ($auditData['risk_level'] === 'high') {
            return 'audit-high-priority';
        }

        if ($auditData['pci_relevant']) {
            return 'audit-pci';
        }

        return 'audit-default';
    }

    /**
     * Get priority for queue processing
     */
    private function getPriority(array $auditData): int
    {
        if ($auditData['risk_level'] === 'high') {
            return 10;
        }

        if ($auditData['pci_relevant']) {
            return 5;
        }

        return 1;
    }

    /**
     * Get audit statistics for dashboard
     */
    public function getStatistics(array $filters = []): array
    {
        $query = AuditLog::query();

        // Apply filters
        if (isset($filters['vault_id'])) {
            $query->forVault($filters['vault_id']);
        }

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->inDateRange($filters['date_from'], $filters['date_to']);
        }

        return [
            'total_operations' => $query->count(),
            'successful_operations' => $query->clone()->byResult('success')->count(),
            'failed_operations' => $query->clone()->byResult('error')->count(),
            'high_risk_operations' => $query->clone()->highRisk()->count(),
            'pci_relevant_operations' => $query->clone()->pciRelevant()->count(),
            'operations_by_type' => $query->clone()
                ->selectRaw('operation, COUNT(*) as count')
                ->groupBy('operation')
                ->pluck('count', 'operation'),
            'operations_by_hour' => $query->clone()
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->pluck('count', 'hour'),
        ];
    }

    /**
     * Export audit logs for compliance
     */
    public function exportForCompliance(array $filters): Collection
    {
        $query = AuditLog::query();

        // Apply filters
        if (isset($filters['vault_id'])) {
            $query->forVault($filters['vault_id']);
        }

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->inDateRange($filters['date_from'], $filters['date_to']);
        }

        if (isset($filters['pci_relevant']) && $filters['pci_relevant']) {
            $query->pciRelevant();
        }

        return $query->get()->map(function ($log) {
            return $log->toComplianceArray();
        });
    }
}