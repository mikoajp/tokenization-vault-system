<?php

namespace App\Services;


use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AuditService
{
    /**
     * Log a manual audit event
     */
    public function logEvent(array $data): AuditLog
    {
        $defaults = [
            'request_id' => request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id() ?? request()->header('X-User-ID'),
            'api_key_id' => request()->header('X-API-Key-ID'),
            'session_id' => session()->getId(),
            'pci_relevant' => true,
            'risk_level' => 'medium',
        ];

        return AuditLog::create(array_merge($defaults, $data));
    }

    /**
     * Get audit summary for a date range
     */
    public function getAuditSummary(Carbon $startDate, Carbon $endDate, ?string $vaultId = null): array
    {
        $query = AuditLog::inDateRange($startDate, $endDate);

        if ($vaultId) {
            $query->byVault($vaultId);
        }

        $logs = $query->get();

        return [
            'total_operations' => $logs->count(),
            'successful_operations' => $logs->where('result', 'success')->count(),
            'failed_operations' => $logs->where('result', 'failure')->count(),
            'operations_by_type' => $logs->groupBy('operation')->map->count(),
            'operations_by_user' => $logs->groupBy('user_id')->map->count(),
            'high_risk_operations' => $logs->where('risk_level', 'high')->count(),
            'unique_users' => $logs->pluck('user_id')->unique()->count(),
            'unique_ips' => $logs->pluck('ip_address')->unique()->count(),
        ];
    }

    /**
     * Get security alerts based on audit logs
     */
    public function getSecurityAlerts(int $hours = 24): Collection
    {
        $since = now()->subHours($hours);
        $alerts = collect();

        // Multiple failed operations from same IP
        $failedOperations = AuditLog::where('created_at', '>=', $since)
            ->where('result', 'failure')
            ->get()
            ->groupBy('ip_address');

        foreach ($failedOperations as $ip => $logs) {
            if ($logs->count() >= 5) {
                $alerts->push([
                    'type' => 'multiple_failures',
                    'severity' => 'high',
                    'message' => "Multiple failed operations from IP: {$ip}",
                    'count' => $logs->count(),
                    'ip_address' => $ip,
                ]);
            }
        }

        $detokenizeOps = AuditLog::where('created_at', '>=', $since)
            ->where('operation', 'detokenize')
            ->get()
            ->groupBy('user_id');

        foreach ($detokenizeOps as $userId => $logs) {
            if ($logs->count() >= 100) {
                $alerts->push([
                    'type' => 'high_volume_detokenize',
                    'severity' => 'medium',
                    'message' => "High volume of detokenize operations by user: {$userId}",
                    'count' => $logs->count(),
                    'user_id' => $userId,
                ]);
            }
        }

        $offHoursOps = AuditLog::where('created_at', '>=', $since)
            ->whereRaw('HOUR(created_at) NOT BETWEEN 8 AND 18')
            ->where('operation', 'detokenize')
            ->get();

        if ($offHoursOps->count() > 0) {
            $alerts->push([
                'type' => 'off_hours_access',
                'severity' => 'medium',
                'message' => "Off-hours detokenize operations detected",
                'count' => $offHoursOps->count(),
            ]);
        }

        return $alerts;
    }

    /**
     * Generate compliance report data
     */
    public function generateComplianceData(Carbon $startDate, Carbon $endDate): array
    {
        $logs = AuditLog::inDateRange($startDate, $endDate)
            ->pciRelevant()
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => $this->getAuditSummary($startDate, $endDate),
            'access_patterns' => [
                'unique_users' => $logs->pluck('user_id')->unique()->count(),
                'unique_ips' => $logs->pluck('ip_address')->unique()->count(),
                'peak_hour' => $logs->groupBy(function ($log) {
                    return $log->created_at->format('H');
                })->map->count()->sortDesc()->keys()->first(),
            ],
            'security_events' => [
                'failed_operations' => $logs->where('result', 'failure')->count(),
                'high_risk_operations' => $logs->where('risk_level', 'high')->count(),
                'compromised_tokens' => $logs->where('operation', 'token_revoke')->count(),
            ],
            'data_retention' => [
                'tokens_created' => $logs->where('operation', 'tokenize')->count(),
                'tokens_deleted' => $logs->where('operation', 'token_delete')->count(),
                'vault_operations' => $logs->whereIn('operation', ['vault_create', 'vault_update', 'vault_delete'])->count(),
            ],
        ];
    }
}
