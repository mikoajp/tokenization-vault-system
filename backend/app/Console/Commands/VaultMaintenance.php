<?php

namespace App\Console\Commands;

namespace App\Console\Commands;

use App\Models\DataRetentionPolicy;
use App\Models\Token;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Carbon\Carbon;

class VaultMaintenance extends Command
{
    protected $signature = 'vault:maintenance
                            {--dry-run : Show what would be done without executing}
                            {--force : Skip confirmations}';

    protected $description = 'Run vault maintenance tasks (cleanup, retention policies)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🔧 Running Vault Maintenance Tasks...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Execute retention policies
        $this->executeRetentionPolicies($dryRun, $force);

        // Clean up expired tokens
        $this->cleanupExpiredTokens($dryRun, $force);

        // Archive old audit logs
        $this->archiveAuditLogs($dryRun, $force);

        $this->info('✅ Maintenance tasks completed.');

        return 0;
    }

    private function executeRetentionPolicies(bool $dryRun, bool $force): void
    {
        $this->info('📋 Checking retention policies...');

        $policies = DataRetentionPolicy::dueForExecution()->get();

        if ($policies->isEmpty()) {
            $this->info('No retention policies due for execution.');
            return;
        }

        foreach ($policies as $policy) {
            $this->info("Processing policy: {$policy->policy_name}");

            $cutoffDate = Carbon::now()->subDays($policy->retention_days);
            $affectedTokens = Token::where('vault_id', $policy->vault_id)
                ->where('created_at', '<', $cutoffDate)
                ->count();

            if ($affectedTokens === 0) {
                $this->info("No tokens to process for this policy.");
                continue;
            }

            $this->info("Found {$affectedTokens} tokens older than {$policy->retention_days} days");

            if (!$dryRun) {
                if (!$force && !$this->confirm("Execute {$policy->action_after_retention} action for {$affectedTokens} tokens?")) {
                    $this->info("Skipped policy: {$policy->policy_name}");
                    continue;
                }

                $this->executeRetentionAction($policy, $cutoffDate);
            }
        }
    }

    private function executeRetentionAction(DataRetentionPolicy $policy, Carbon $cutoffDate): void
    {
        $tokens = Token::where('vault_id', $policy->vault_id)
            ->where('created_at', '<', $cutoffDate)
            ->get();

        $processedCount = 0;

        foreach ($tokens as $token) {
            switch ($policy->action_after_retention) {
                case 'delete':
                    $token->delete();
                    break;

                case 'archive':
                    $token->update(['status' => 'archived']);
                    break;

                case 'anonymize':
                    $token->update([
                        'encrypted_data' => encrypt('ANONYMIZED'),
                        'data_hash' => hash('sha256', 'ANONYMIZED'),
                        'status' => 'anonymized'
                    ]);
                    break;
            }

            $processedCount++;
        }

        $policy->logExecution([
            'processed_tokens' => $processedCount,
            'action' => $policy->action_after_retention,
            'cutoff_date' => $cutoffDate->toISOString(),
        ]);

        $this->info("Processed {$processedCount} tokens with action: {$policy->action_after_retention}");
    }

    private function cleanupExpiredTokens(bool $dryRun, bool $force): void
    {
        $this->info('🧹 Cleaning up expired tokens...');

        $expiredTokens = Token::where('expires_at', '<', Carbon::now())
            ->where('status', '!=', 'expired')
            ->get();

        if ($expiredTokens->isEmpty()) {
            $this->info('No expired tokens to clean up.');
            return;
        }

        $this->info("Found {$expiredTokens->count()} expired tokens");

        if (!$dryRun) {
            if (!$force && !$this->confirm("Mark {$expiredTokens->count()} tokens as expired?")) {
                return;
            }

            foreach ($expiredTokens as $token) {
                $token->update(['status' => 'expired']);
            }

            $this->info("Marked {$expiredTokens->count()} tokens as expired");
        }
    }

    private function archiveAuditLogs(bool $dryRun, bool $force): void
    {
        $this->info('📚 Checking audit log archival...');

        $retentionDays = config('audit.retention_days', 2555);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $oldLogs = AuditLog::where('created_at', '<', $cutoffDate)->count();

        if ($oldLogs === 0) {
            $this->info('No audit logs require archival.');
            return;
        }

        $this->info("Found {$oldLogs} audit logs older than {$retentionDays} days");

        if (!$dryRun) {
            if (!$force && !$this->confirm("Archive {$oldLogs} old audit logs?")) {
                return;
            }

            $deleted = AuditLog::where('created_at', '<', $cutoffDate)->delete();

            $this->info("Archived {$deleted} audit logs");
        }
    }
}
