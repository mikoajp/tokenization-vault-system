<?php

namespace App\Services;

use App\Jobs\ProcessAuditLog;
use App\Models\AuditLog;
use App\Models\SecurityAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class AuditService
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

            Log::debug('Audit event queued', [
                'audit_id' => $auditId,
                'operation' => $data['operation'] ?? 'unknown',
                'queue' => $this->determineQueue($auditData),
            ]);

            return $auditId;

        } catch (\Exception $e) {
            Log::error('Failed to queue audit event', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return $this->logEventSync($data);
        }
    }

    /**
     * Synchronous logging as fallback
     */
    public function logEventSync(array $data): string
    {
        $auditId = \Illuminate\Support\Str::uuid();
        $auditData = $this->prepareAuditData($data, $auditId);

        try {
            $auditLog = AuditLog::create($auditData);
            return $auditLog->id;
        } catch (\Exception $e) {
            Log::critical('Critical: Both async and sync audit logging failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Prepare audit data with defaults and context
     */
    private function prepareAuditData(array $data, string $auditId): array
    {
        $defaults = [
            'id' => $auditId,
            'request_id' => request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id() ?? request()->header('X-User-ID'),
            'api_key_id' => request()->header('X-API-Key-ID'),
            'session_id' => session()->getId(),
            'pci_relevant' => true,
            'risk_level' => 'medium',
            'created_at' => now()->toISOString(),
            'metadata' => [],
        ];

        return array_merge($defaults, $data);
    }

    /**
     * Determine which queue to use based on data priority
     */
    private function determineQueue(array $data): string
    {
        $riskLevel = $data['risk_level'] ?? 'medium';
        $result = $data['result'] ?? 'success';

        if ($riskLevel === 'critical' || $result === 'failure') {
            return 'audit_logs_critical';
        }

        if ($riskLevel === 'high') {
            return 'audit_logs_high';
        }

        return 'audit_logs';
    }

    /**
     * Get priority for queue processing
     */
    private function getPriority(array $data): int
    {
        $riskLevel = $data['risk_level'] ?? 'medium';
        $result = $data['result'] ?? 'success';

        if ($riskLevel === 'critical') return 10;
        if ($result === 'failure') return 8;
        if ($riskLevel === 'high') return 7;
        if (($data['operation'] ?? '') === 'detokenize') return 6;

        return 5;
    }

    /**
     * Get audit summary with caching
     */
    public function getAuditSummary(Carbon $startDate, Carbon $endDate, ?string $vaultId = null): array
    {
        $cacheKey = $this->generateSummaryCacheKey($startDate, $endDate, $vaultId);

        return Cache::remember($cacheKey, 300, function () use ($startDate, $endDate, $vaultId) {
            return $this->calculateAuditSummary($startDate, $endDate, $vaultId);
        });
    }

    /**
     * Calculate audit summary from database
     */
    private function calculateAuditSummary(Carbon $startDate, Carbon $endDate, ?string $vaultId = null): array
    {
        $query = AuditLog::whereBetween('created_at', [$startDate, $endDate]);

        if ($vaultId) {
            $query->where('vault_id', $vaultId);
        }

        $logs = $query->get();

        return [
            'total_operations' => $logs->count(),
            'successful_operations' => $logs->where('result', 'success')->count(),
            'failed_operations' => $logs->where('result', 'failure')->count(),
            'operations_by_type' => $logs->groupBy('operation')->map->count(),
            'operations_by_user' => $logs->groupBy('user_id')->map->count(),
            'high_risk_operations' => $logs->whereIn('risk_level', ['high', 'critical'])->count(),
            'unique_users' => $logs->pluck('user_id')->unique()->count(),
            'unique_ips' => $logs->pluck('ip_address')->unique()->count(),
            'pci_relevant_operations' => $logs->where('pci_relevant', true)->count(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get security alerts from cache/database
     */
    public function getSecurityAlerts(int $hours = 24): Collection
    {
        $cacheKey = "security_alerts_cache:{$hours}";

        return Cache::remember($cacheKey, 300, function () use ($hours) {
            $since = now()->subHours($hours);

            return SecurityAlert::where('created_at', '>=', $since)
                ->where('status', 'active')
                ->orderBy('severity', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($alert) {
                    return [
                        'id' => $alert->id,
                        'type' => $alert->type,
                        'severity' => $alert->severity,
                        'message' => $alert->message,
                        'count' => $alert->count,
                        'ip_address' => $alert->ip_address,
                        'user_id' => $alert->user_id,
                        'first_occurrence' => $alert->first_occurrence,
                        'last_occurrence' => $alert->last_occurrence,
                        'created_at' => $alert->created_at,
                    ];
                });
        });
    }

    /**
     * Generate compliance report asynchronously
     */
    public function generateComplianceReport(Carbon $startDate, Carbon $endDate, string $reportType = 'pci_dss'): string
    {
        $reportId = \Illuminate\Support\Str::uuid();

        Queue::pushOn('compliance_reports', new \App\Jobs\GenerateComplianceReport([
            'report_id' => $reportId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'report_type' => $reportType,
            'requested_by' => auth()->id(),
        ]));

        return $reportId;
    }

    /**
     * Generate immediate compliance data (synchronous)
     */
    public function generateComplianceData(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "compliance_data:" . $startDate->format('Y-m-d') . ":" . $endDate->format('Y-m-d');

        return Cache::remember($cacheKey, 1800, function () use ($startDate, $endDate) {
            $logs = AuditLog::whereBetween('created_at', [$startDate, $endDate])
                ->where('pci_relevant', true)
                ->get();

            return [
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'summary' => $this->calculateAuditSummary($startDate, $endDate),
                'access_patterns' => [
                    'unique_users' => $logs->pluck('user_id')->unique()->count(),
                    'unique_ips' => $logs->pluck('ip_address')->unique()->count(),
                    'peak_hour' => $logs->groupBy(function ($log) {
                        return $log->created_at->format('H');
                    })->map->count()->sortDesc()->keys()->first(),
                ],
                'security_events' => [
                    'failed_operations' => $logs->where('result', 'failure')->count(),
                    'high_risk_operations' => $logs->whereIn('risk_level', ['high', 'critical'])->count(),
                    'compromised_tokens' => $logs->where('operation', 'token_revoke')->count(),
                ],
                'data_retention' => [
                    'tokens_created' => $logs->where('operation', 'tokenize')->count(),
                    'tokens_deleted' => $logs->where('operation', 'token_delete')->count(),
                    'vault_operations' => $logs->whereIn('operation', ['vault_create', 'vault_update', 'vault_delete'])->count(),
                ],
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get real-time metrics from cache
     */
    public function getRealTimeMetrics(): array
    {
        $today = now()->format('Y-m-d');

        return [
            'today' => [
                'total_operations' => Cache::get("audit_metrics:{$today}:total", 0),
                'tokenize_operations' => Cache::get("audit_metrics:{$today}:operation:tokenize", 0),
                'detokenize_operations' => Cache::get("audit_metrics:{$today}:operation:detokenize", 0),
                'successful_operations' => Cache::get("audit_metrics:{$today}:result:success", 0),
                'failed_operations' => Cache::get("audit_metrics:{$today}:result:failure", 0),
            ],
            'queue_status' => $this->getQueueStatus(),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get queue status for monitoring
     */
    private function getQueueStatus(): array
    {
        try {
            return [
                'audit_logs' => 0,
                'security_alerts' => 0,
                'notifications' => 0,
                'status' => 'healthy',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate cache key for summary
     */
    private function generateSummaryCacheKey(Carbon $startDate, Carbon $endDate, ?string $vaultId): string
    {
        $key = "audit_summary:" . $startDate->format('Y-m-d') . ":" . $endDate->format('Y-m-d');

        if ($vaultId) {
            $key .= ":" . $vaultId;
        }

        return $key;
    }

    /**
     * Clear relevant caches when new data is processed
     */
    public function clearRelevantCaches(array $auditData): void
    {
        $today = now()->format('Y-m-d');
        $patterns = [
            "audit_summary:*{$today}*",
            "security_alerts_cache:*",
            "compliance_data:*{$today}*",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
