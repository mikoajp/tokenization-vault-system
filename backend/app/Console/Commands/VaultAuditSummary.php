<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Vault;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class VaultAuditSummary extends Command
{
    protected $signature = 'vault:audit-summary
                            {--days=7 : Number of days to analyze}
                            {--vault= : Specific vault ID to analyze}
                            {--alerts : Show security alerts}';

    protected $description = 'Display audit summary and security analysis';

    public function handle(AuditService $auditService): int
    {
        $days = (int) $this->option('days');
        $vaultId = $this->option('vault');
        $showAlerts = $this->option('alerts');

        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $this->info("📊 Audit Summary for last {$days} days");
        $this->info("Period: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        if ($vaultId) {
            $vault = Vault::find($vaultId);
            if (!$vault) {
                $this->error("Vault {$vaultId} not found.");
                return 1;
            }
            $this->info("Vault: {$vault->name}");
        }

        $summary = $auditService->getAuditSummary($startDate, $endDate, $vaultId);

        $this->displaySummaryTable($summary);

        if ($showAlerts) {
            $this->displaySecurityAlerts($auditService);
        }

        return 0;
    }

    private function displaySummaryTable(array $summary): void
    {
        $this->info('');
        $this->info('📈 Operation Summary:');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Operations', number_format($summary['total_operations'])],
                ['Successful', number_format($summary['successful_operations'])],
                ['Failed', number_format($summary['failed_operations'])],
                ['High Risk Operations', number_format($summary['high_risk_operations'])],
                ['Unique Users', number_format($summary['unique_users'])],
                ['Unique IP Addresses', number_format($summary['unique_ips'])],
            ]
        );

        if (!empty($summary['operations_by_type'])) {
            $this->info('');
            $this->info('🔧 Operations by Type:');

            $operationData = [];
            foreach ($summary['operations_by_type'] as $operation => $count) {
                $operationData[] = [$operation, number_format($count)];
            }

            $this->table(['Operation', 'Count'], $operationData);
        }
    }

    private function displaySecurityAlerts(AuditService $auditService): void
    {
        $alerts = $auditService->getSecurityAlerts(24);

        if ($alerts->isEmpty()) {
            $this->info('');
            $this->info('✅ No security alerts in the last 24 hours.');
            return;
        }

        $this->info('');
        $this->warn('⚠️  Security Alerts:');

        $alertData = [];
        foreach ($alerts as $alert) {
            $alertData[] = [
                $alert['type'],
                $alert['severity'],
                $alert['message'],
                $alert['count'] ?? 'N/A'
            ];
        }

        $this->table(['Type', 'Severity', 'Message', 'Count'], $alertData);
    }
}
