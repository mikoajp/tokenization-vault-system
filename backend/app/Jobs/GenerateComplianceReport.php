<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\ComplianceReport;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class GenerateComplianceReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout w sekundach (2 godziny)
     */
    public $timeout = 7200;

    /**
     * Liczba prób
     */
    public $tries = 2;

    /**
     * Czas oczekiwania przed retry (w sekundach)
     */
    public $retryAfter = 300;

    /**
     * Dane do generowania raportu
     */
    private array $reportParams;

    /**
     * Model raportu
     */
    private ComplianceReport $reportModel;

    public function __construct(array $reportParams)
    {
        $this->reportParams = $reportParams;

        $this->onQueue('compliance_reports');
    }

    /**
     * Execute the job
     */
    public function handle(AuditService $auditService): void
    {
        try {
            Log::info('Starting compliance report generation', [
                'report_id' => $this->reportParams['report_id'],
                'report_type' => $this->reportParams['report_type'] ?? 'pci_dss',
                'date_range' => [
                    'start' => $this->reportParams['start_date'],
                    'end' => $this->reportParams['end_date']
                ]
            ]);

            $this->initializeReportModel();

            $this->reportModel->markAsProcessing('Collecting audit data...');

            $this->updateProgress(25, 'Collecting audit data...');
            $auditData = $this->collectAuditData($auditService);

            $this->updateProgress(50, 'Analyzing compliance data...');
            $analysis = $this->performComplianceAnalysis($auditData);

            $this->updateProgress(75, 'Generating report document...');
            $documentInfo = $this->generateReportDocument($auditData, $analysis);

            $this->updateProgress(100, 'Finalizing report...');
            $this->finalizeReport($documentInfo, $analysis);

            $this->sendNotifications();

            Log::info('Compliance report generated successfully', [
                'report_id' => $this->reportParams['report_id'],
                'file_path' => $documentInfo['file_path'],
                'duration' => $this->reportModel->duration,
            ]);

        } catch (\Exception $e) {
            $this->handleJobFailure($e);
            throw $e;
        }
    }

    /**
     * Inicjalizuj model raportu
     */
    private function initializeReportModel(): void
    {
        $this->reportModel = ComplianceReport::where('id', $this->reportParams['report_id'])
            ->orWhere('report_id', $this->reportParams['report_id'])
            ->firstOrFail();

        $this->reportModel->markAsProcessing('Report generation started');
    }

    /**
     * Zbierz dane audytu dla raportu
     */
    private function collectAuditData(AuditService $auditService): array
    {
        $startDate = Carbon::parse($this->reportParams['start_date']);
        $endDate = Carbon::parse($this->reportParams['end_date']);
        $vaultId = $this->reportParams['vault_id'] ?? null;

        $complianceData = $auditService->generateComplianceData($startDate, $endDate);

        $query = AuditLog::inDateRange($startDate, $endDate)->pciRelevant();

        if ($vaultId) {
            $query->byVault($vaultId);
        }

        $auditLogs = $query->orderBy('created_at', 'desc')->get();

        return [
            'compliance_summary' => $complianceData,
            'audit_logs' => $auditLogs,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
                'vault_id' => $vaultId,
            ],
            'totals' => [
                'total_logs' => $auditLogs->count(),
                'unique_users' => $auditLogs->pluck('user_id')->unique()->count(),
                'unique_vaults' => $auditLogs->pluck('vault_id')->unique()->count(),
                'operations_by_type' => $auditLogs->groupBy('operation')->map->count(),
                'high_risk_count' => $auditLogs->where('risk_level', 'high')->count(),
                'critical_count' => $auditLogs->where('risk_level', 'critical')->count(),
                'failure_count' => $auditLogs->where('result', 'failure')->count(),
            ]
        ];
    }

    /**
     * Wykonaj analizę zgodności z przepisami
     */
    private function performComplianceAnalysis(array $auditData): array
    {
        $reportType = $this->reportParams['report_type'] ?? 'pci_dss';
        $logs = $auditData['audit_logs'];

        $analysis = [
            'compliance_score' => 0,
            'violations' => [],
            'recommendations' => [],
            'risk_assessment' => 'LOW',
            'findings' => [],
        ];

        switch ($reportType) {
            case 'pci_dss':
                $analysis = $this->analyzePciDssCompliance($logs, $analysis);
                break;
            case 'sox':
                $analysis = $this->analyzeSoxCompliance($logs, $analysis);
                break;
            case 'gdpr':
                $analysis = $this->analyzeGdprCompliance($logs, $analysis);
                break;
        }

        return $analysis;
    }

    /**
     * Analiza zgodności z PCI DSS
     */
    private function analyzePciDssCompliance($logs, array $analysis): array
    {
        $totalLogs = $logs->count();
        $violations = [];
        $recommendations = [];
        $score = 100;

        // 1. Sprawdź dostęp w godzinach poza pracą (PCI DSS 7.1)
        $offHoursAccess = $logs->filter(function ($log) {
            $hour = $log->created_at->hour;
            return $hour < 8 || $hour > 18;
        });

        if ($offHoursAccess->count() > 0) {
            $violations[] = [
                'requirement' => 'PCI DSS 7.1 - Access Control',
                'description' => 'Detected ' . $offHoursAccess->count() . ' access attempts outside business hours',
                'severity' => 'medium',
                'count' => $offHoursAccess->count(),
            ];
            $score -= 10;
        }

        // 2. Sprawdź nieudane próby dostępu (PCI DSS 8.1)
        $failedAttempts = $logs->where('result', 'failure');
        if ($failedAttempts->count() > 50) {
            $violations[] = [
                'requirement' => 'PCI DSS 8.1 - Authentication',
                'description' => 'High number of failed authentication attempts: ' . $failedAttempts->count(),
                'severity' => 'high',
                'count' => $failedAttempts->count(),
            ];
            $score -= 20;
        }

        // 3. Sprawdź operacje wysokiego ryzyka (PCI DSS 10.2)
        $highRiskOps = $logs->whereIn('risk_level', ['high', 'critical']);
        if ($highRiskOps->count() > $totalLogs * 0.1) { // Więcej niż 10%
            $violations[] = [
                'requirement' => 'PCI DSS 10.2 - Audit Logs',
                'description' => 'High percentage of high-risk operations detected',
                'severity' => 'medium',
                'count' => $highRiskOps->count(),
            ];
            $score -= 15;
        }
        $bulkDetokenize = $logs->where('operation', 'detokenize')
            ->groupBy('user_id')
            ->filter(function ($userLogs) {
                return $userLogs->count() > 100;
            });

        if ($bulkDetokenize->count() > 0) {
            $violations[] = [
                'requirement' => 'PCI DSS 3.4 - Data Protection',
                'description' => 'Detected bulk detokenization operations by ' . $bulkDetokenize->count() . ' users',
                'severity' => 'critical',
                'count' => $bulkDetokenize->count(),
            ];
            $score -= 30;
        }

        if ($offHoursAccess->count() > 0) {
            $recommendations[] = 'Implement additional monitoring and approval processes for off-hours access';
        }

        if ($failedAttempts->count() > 10) {
            $recommendations[] = 'Review and strengthen authentication mechanisms';
        }

        if ($bulkDetokenize->count() > 0) {
            $recommendations[] = 'Implement approval workflows for bulk detokenization operations';
        }

        $analysis['compliance_score'] = max(0, $score);
        $analysis['violations'] = $violations;
        $analysis['recommendations'] = $recommendations;
        $analysis['risk_assessment'] = $this->calculateRiskLevel($score);

        return $analysis;
    }

    /**
     * Analiza zgodności z SOX
     */
    private function analyzeSoxCompliance($logs, array $analysis): array
    {
        $violations = [];
        $score = 100;

        $userOperations = $logs->groupBy('user_id');
        foreach ($userOperations as $userId => $userLogs) {
            $operations = $userLogs->pluck('operation')->unique();
            if ($operations->contains('tokenize') && $operations->contains('detokenize')) {
                $violations[] = [
                    'requirement' => 'SOX - Segregation of Duties',
                    'description' => "User {$userId} performed both tokenize and detokenize operations",
                    'severity' => 'high',
                    'user_id' => $userId,
                ];
                $score -= 25;
            }
        }

        $analysis['compliance_score'] = max(0, $score);
        $analysis['violations'] = $violations;
        $analysis['risk_assessment'] = $this->calculateRiskLevel($score);

        return $analysis;
    }

    /**
     * Analiza zgodności z GDPR
     */
    private function analyzeGdprCompliance($logs, array $analysis): array
    {
        $violations = [];
        $score = 100;
        $dataAccessLogs = $logs->where('operation', 'detokenize');
        if ($dataAccessLogs->count() === 0) {
            $violations[] = [
                'requirement' => 'GDPR Art. 30 - Records of processing',
                'description' => 'No data access logs found for the reporting period',
                'severity' => 'medium',
            ];
            $score -= 15;
        }

        $analysis['compliance_score'] = max(0, $score);
        $analysis['violations'] = $violations;
        $analysis['risk_assessment'] = $this->calculateRiskLevel($score);

        return $analysis;
    }

    /**
     * Wygeneruj dokument raportu
     */
    private function generateReportDocument(array $auditData, array $analysis): array
    {
        $reportType = $this->reportParams['report_type'] ?? 'pci_dss';
        $timestamp = now()->format('Y-m-d_H-i-s');
        $fileName = "compliance_report_{$reportType}_{$timestamp}.pdf";
        $filePath = "compliance_reports/{$fileName}";

        // Dla demonstracji - generujemy JSON, w prawdziwym systemie byłby to PDF
        $reportContent = [
            'report_metadata' => [
                'id' => $this->reportParams['report_id'],
                'type' => $reportType,
                'generated_at' => now()->toISOString(),
                'period' => $auditData['date_range'],
                'generated_by' => $this->reportParams['requested_by'],
            ],
            'executive_summary' => [
                'compliance_score' => $analysis['compliance_score'],
                'risk_level' => $analysis['risk_assessment'],
                'total_violations' => count($analysis['violations']),
                'total_audit_events' => $auditData['totals']['total_logs'],
            ],
            'detailed_analysis' => $analysis,
            'audit_summary' => $auditData['compliance_summary'],
            'statistics' => $auditData['totals'],
            'violations' => $analysis['violations'],
            'recommendations' => $analysis['recommendations'],
        ];

        $jsonContent = json_encode($reportContent, JSON_PRETTY_PRINT);
        $fullPath = storage_path('app/' . $filePath);

        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, $jsonContent);

        return [
            'file_path' => $filePath,
            'full_path' => $fullPath,
            'file_name' => $fileName,
            'file_size' => filesize($fullPath),
            'content_type' => 'application/pdf',
        ];
    }

    /**
     * Finalizuj raport
     */
    private function finalizeReport(array $documentInfo, array $analysis): void
    {
        $downloadUrl = route('compliance.reports.download', [
            'reportId' => $this->reportParams['report_id']
        ]);

        $this->reportModel->markCompleted(
            $documentInfo['file_path'],
            hash_file('sha256', $documentInfo['full_path']),
            [
                'compliance_score' => $analysis['compliance_score'],
                'risk_assessment' => $analysis['risk_assessment'],
                'violations_count' => count($analysis['violations']),
                'recommendations_count' => count($analysis['recommendations']),
                'file_size' => $documentInfo['file_size'],
            ]
        );

        $this->reportModel->update([
            'download_url' => $downloadUrl,
            'file_size' => $documentInfo['file_size'],
        ]);
    }

    /**
     * Wyślij powiadomienia
     */
    private function sendNotifications(): void
    {
        if (!empty($this->reportParams['email'])) {
            Queue::pushOn('notifications', new SendReportReadyEmail(
                $this->reportParams['email'],
                $this->reportModel
            ));
        }
        if (!empty($this->reportParams['requested_by'])) {
            Queue::pushOn('notifications', new SendUserNotification(
                $this->reportParams['requested_by'],
                'Compliance report ready',
                "Your {$this->reportModel->report_type} compliance report has been generated and is ready for download."
            ));
        }
    }

    /**
     * Aktualizuj postęp
     */
    private function updateProgress(int $progress, string $message): void
    {
        $this->reportModel->updateProgress($progress, $message);

        Log::debug('Compliance report progress updated', [
            'report_id' => $this->reportParams['report_id'],
            'progress' => $progress,
            'message' => $message,
        ]);
    }

    /**
     * Oblicz poziom ryzyka na podstawie wyniku
     */
    private function calculateRiskLevel(int $score): string
    {
        return match (true) {
            $score >= 90 => 'LOW',
            $score >= 70 => 'MEDIUM',
            $score >= 50 => 'HIGH',
            default => 'CRITICAL',
        };
    }

    /**
     * Obsłuż błąd zadania
     */
    private function handleJobFailure(\Exception $e): void
    {
        Log::error('Compliance report generation failed', [
            'report_id' => $this->reportParams['report_id'],
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (isset($this->reportModel)) {
            $this->reportModel->markFailed($e->getMessage());
        }
    }

    /**
     * Handle job failure permanently
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Compliance report job failed permanently', [
            'report_id' => $this->reportParams['report_id'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'params' => $this->reportParams,
        ]);

        try {
            if (isset($this->reportParams['report_id'])) {
                $report = ComplianceReport::where('id', $this->reportParams['report_id'])
                    ->orWhere('report_id', $this->reportParams['report_id'])
                    ->first();

                if ($report) {
                    $report->markFailed($exception->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Could not mark compliance report as failed', [
                'report_id' => $this->reportParams['report_id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }

        if (!empty($this->reportParams['email'])) {
            try {
                Queue::pushOn('notifications', new SendReportFailedEmail(
                    $this->reportParams['email'],
                    $this->reportParams['report_id'] ?? 'unknown',
                    $exception->getMessage()
                ));
            } catch (\Exception $e) {
                Log::error('Could not send failure notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
