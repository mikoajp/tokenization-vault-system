<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ProcessAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 60;

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * Delay before retrying failed job (seconds)
     */
    public int $retryAfter = 30;

    /**
     * Create a new job instance
     */
    public function __construct(
        private array $auditData,
        private bool $triggerAlerts = true
    ) {
        $this->onQueue($this->determineQueue());
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            Log::info('Processing audit log', [
                'operation' => $this->auditData['operation'] ?? 'unknown',
                'vault_id' => $this->auditData['vault_id'] ?? null,
                'user_id' => $this->auditData['user_id'] ?? null,
            ]);

            $auditLog = AuditLog::create($this->auditData);

            if ($this->triggerAlerts && $this->shouldTriggerAlerts()) {
                Queue::push(new ProcessSecurityAlert($auditLog));
            }

            if ($this->auditData['pci_relevant'] ?? false) {
                Queue::pushOn('compliance', new ProcessComplianceEvent($auditLog));
            }

            $this->updateMetrics();

            Log::info('Audit log processed successfully', ['audit_id' => $auditLog->id]);

        } catch (\Exception $e) {
            Log::error('Failed to process audit log', [
                'error' => $e->getMessage(),
                'audit_data' => $this->auditData,
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                Queue::pushOn('notifications', new SendSystemAlert([
                    'type' => 'audit_processing_failed',
                    'message' => 'Critical: Audit log processing failed after all retries',
                    'data' => $this->auditData,
                    'error' => $e->getMessage(),
                ]));
            }

            throw $e;
        }
    }

    /**
     * Determine which queue to use based on priority
     */
    private function determineQueue(): string
    {
        $riskLevel = $this->auditData['risk_level'] ?? 'medium';
        $result = $this->auditData['result'] ?? 'success';

        if ($riskLevel === 'critical' || $result === 'failure') {
            return 'audit_logs_critical';
        }

        if ($riskLevel === 'high') {
            return 'audit_logs_high';
        }

        return 'audit_logs';
    }

    /**
     * Check if security alerts should be triggered
     */
    private function shouldTriggerAlerts(): bool
    {
        $operation = $this->auditData['operation'] ?? '';
        $result = $this->auditData['result'] ?? 'success';
        $riskLevel = $this->auditData['risk_level'] ?? 'medium';

        return $result === 'failure' ||
            $riskLevel === 'high' ||
            $riskLevel === 'critical' ||
            in_array($operation, ['detokenize', 'bulk_detokenize', 'token_revoke']);
    }

    /**
     * Update real-time metrics in cache
     */
    private function updateMetrics(): void
    {
        $operation = $this->auditData['operation'] ?? 'unknown';
        $result = $this->auditData['result'] ?? 'unknown';
        $today = now()->format('Y-m-d');

        cache()->increment("audit_metrics:{$today}:total");
        cache()->increment("audit_metrics:{$today}:operation:{$operation}");
        cache()->increment("audit_metrics:{$today}:result:{$result}");

        cache()->put("audit_metrics:{$today}:expires", now()->addDays(7), now()->addDays(7));
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Audit log job failed permanently', [
            'audit_data' => $this->auditData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        Queue::pushOn('notifications', new SendSystemAlert([
            'type' => 'critical_audit_failure',
            'message' => 'CRITICAL: Audit log could not be processed',
            'audit_data' => $this->auditData,
            'error' => $exception->getMessage(),
            'severity' => 'critical',
        ]));
    }
}
