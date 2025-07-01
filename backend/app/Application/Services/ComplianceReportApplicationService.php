<?php

namespace App\Application\Services;

use App\Jobs\GenerateComplianceReport;
use App\Infrastructure\Persistence\Eloquent\Models\ComplianceReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplianceReportApplicationService
{
    /**
     * Create new compliance report
     */
    public function createReport(array $params): ComplianceReport
    {
        $reportId = Str::uuid();

        $report = ComplianceReport::create([
            'report_type' => $params['report_type'] ?? 'pci_dss',
            'report_name' => $params['report_name'] ?? $this->generateReportName($params),
            'period_start' => Carbon::parse($params['period_start']),
            'period_end' => Carbon::parse($params['period_end']),
            'status' => 'pending',
            'filters_applied' => $params['filters'] ?? [],
            'generated_by' => Auth::id(),
            'access_granted_to' => $params['access_granted_to'] ?? [],
            'expires_at' => isset($params['expires_at']) 
                ? Carbon::parse($params['expires_at']) 
                : now()->addDays(30),
            'progress' => 0,
            'status_message' => 'Report queued for generation',
        ]);

        // Queue report generation
        $job = new GenerateComplianceReport($report);
        $jobId = Queue::push($job);

        $report->update(['queue_job_id' => $jobId]);

        return $report;
    }

    /**
     * Generate report name based on parameters
     */
    private function generateReportName(array $params): string
    {
        $type = $params['report_type'] ?? 'compliance';
        $start = Carbon::parse($params['period_start'])->format('Y-m-d');
        $end = Carbon::parse($params['period_end'])->format('Y-m-d');
        
        return ucfirst($type) . "_Report_{$start}_to_{$end}";
    }

    /**
     * Get reports accessible by user
     */
    public function getUserReports(string $userId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = ComplianceReport::accessibleBy($userId);

        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate(25);
    }

    /**
     * Update report progress
     */
    public function updateProgress(string $reportId, int $progress, string $message = null): void
    {
        $report = ComplianceReport::findOrFail($reportId);
        
        $updates = ['progress' => min(100, max(0, $progress))];
        
        if ($message) {
            $updates['status_message'] = $message;
        }

        if ($progress >= 100) {
            $updates['status'] = 'completed';
        }

        $report->update($updates);
    }

    /**
     * Mark report as completed
     */
    public function markCompleted(string $reportId, string $filePath, array $statistics = []): void
    {
        $report = ComplianceReport::findOrFail($reportId);
        
        $fileHash = hash_file('sha256', $filePath);
        
        $report->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_hash' => $fileHash,
            'progress' => 100,
            'status_message' => 'Report generated successfully',
            'summary_statistics' => $statistics,
            'total_records' => $statistics['total_records'] ?? 0,
            'generation_time_seconds' => $statistics['generation_time'] ?? 0,
        ]);
    }

    /**
     * Mark report as failed
     */
    public function markFailed(string $reportId, string $errorMessage, array $errorDetails = []): void
    {
        $report = ComplianceReport::findOrFail($reportId);
        
        $report->update([
            'status' => 'failed',
            'status_message' => $errorMessage,
            'error_details' => $errorDetails,
            'retry_count' => ($report->retry_count ?? 0) + 1
        ]);
    }

    /**
     * Retry failed report generation
     */
    public function retryReport(string $reportId): bool
    {
        $report = ComplianceReport::findOrFail($reportId);
        
        if (!$report->canRetry()) {
            return false;
        }

        $report->update([
            'status' => 'pending',
            'status_message' => 'Report queued for retry',
            'progress' => 0,
            'error_details' => null,
        ]);

        // Queue report generation
        $job = new GenerateComplianceReport($report);
        $jobId = Queue::push($job);

        $report->update(['queue_job_id' => $jobId]);

        return true;
    }

    /**
     * Grant access to report
     */
    public function grantAccess(string $reportId, array $userIds): void
    {
        $report = ComplianceReport::findOrFail($reportId);
        
        if ($report->generated_by !== Auth::id()) {
            throw new \Exception('Only report owner can grant access');
        }

        $report->grantAccessTo($userIds);
    }

    /**
     * Revoke access from report
     */
    public function revokeAccess(string $reportId, array $userIds): void
    {
        $report = ComplianceReport::findOrFail($reportId);
        
        if ($report->generated_by !== Auth::id()) {
            throw new \Exception('Only report owner can revoke access');
        }

        $report->revokeAccessFrom($userIds);
    }

    /**
     * Download report file
     */
    public function downloadReport(string $reportId, string $userId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $report = ComplianceReport::findOrFail($reportId);

        if (!$report->isAccessibleBy($userId)) {
            throw new \Exception('Access denied');
        }

        if (!$report->isCompleted() || !$report->file_path) {
            throw new \Exception('Report not ready for download');
        }

        if ($report->isExpired()) {
            throw new \Exception('Report has expired');
        }

        if (!$report->verifyFileIntegrity()) {
            throw new \Exception('Report file integrity check failed');
        }

        return Storage::download($report->file_path, $report->report_name . '.pdf');
    }

    /**
     * Delete report and cleanup files
     */
    public function deleteReport(string $reportId, string $userId): void
    {
        $report = ComplianceReport::findOrFail($reportId);

        if (!$report->isAccessibleBy($userId)) {
            throw new \Exception('Access denied');
        }

        $report->cleanup();
        $report->delete();
    }

    /**
     * Get report statistics
     */
    public function getReportStatistics(array $filters = []): array
    {
        $query = ComplianceReport::query();

        if (isset($filters['user_id'])) {
            $query->accessibleBy($filters['user_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return [
            'total_reports' => $query->count(),
            'completed_reports' => $query->clone()->completed()->count(),
            'failed_reports' => $query->clone()->failed()->count(),
            'in_progress_reports' => $query->clone()->inProgress()->count(),
            'reports_by_type' => $query->clone()
                ->selectRaw('report_type, COUNT(*) as count')
                ->groupBy('report_type')
                ->pluck('count', 'report_type'),
            'reports_by_status' => $query->clone()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'avg_generation_time' => $query->clone()
                ->completed()
                ->avg('generation_time_seconds'),
        ];
    }

    /**
     * Cleanup expired reports
     */
    public function cleanupExpiredReports(): int
    {
        $expiredReports = ComplianceReport::expired()->get();
        
        $cleaned = 0;
        foreach ($expiredReports as $report) {
            $report->cleanup();
            $report->delete();
            $cleaned++;
        }

        return $cleaned;
    }

    /**
     * Get available report types
     */
    public function getAvailableReportTypes(): array
    {
        return [
            'audit_summary' => [
                'name' => 'Audit Summary Report',
                'description' => 'Summary of all audit activities',
                'estimated_time' => '5-10 minutes',
            ],
            'token_usage' => [
                'name' => 'Token Usage Report',
                'description' => 'Detailed token usage statistics',
                'estimated_time' => '10-15 minutes',
            ],
            'vault_activity' => [
                'name' => 'Vault Activity Report',
                'description' => 'Activity report for specific vaults',
                'estimated_time' => '5-10 minutes',
            ],
            'compliance_overview' => [
                'name' => 'Compliance Overview',
                'description' => 'Overall compliance status and metrics',
                'estimated_time' => '15-20 minutes',
            ],
            'pci_dss' => [
                'name' => 'PCI DSS Compliance Report',
                'description' => 'PCI DSS specific compliance report',
                'estimated_time' => '20-30 minutes',
            ],
            'security_incidents' => [
                'name' => 'Security Incidents Report',
                'description' => 'Security alerts and incidents summary',
                'estimated_time' => '10-15 minutes',
            ],
        ];
    }
}