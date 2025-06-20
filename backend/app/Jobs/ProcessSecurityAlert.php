<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\SecurityAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ProcessSecurityAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 5;
    public int $retryAfter = 15;

    public function __construct(private AuditLog $auditLog)
    {
        $this->onQueue('security_alerts');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            Log::info('Processing security alert check', [
                'audit_id' => $this->auditLog->id,
                'operation' => $this->auditLog->operation,
                'ip_address' => $this->auditLog->ip_address,
            ]);

            $alerts = collect();

            // 1. Check for multiple failures from same IP
            $this->checkMultipleFailures($alerts);

            // 2. Check for high volume detokenization
            $this->checkHighVolumeDetokenize($alerts);

            // 3. Check for off-hours access
            $this->checkOffHoursAccess($alerts);

            // 4. Check for suspicious patterns
            $this->checkSuspiciousPatterns($alerts);

            // 5. Send notifications for new alerts
            // $this->processAlerts($alerts);

            Log::info('Security alert processing completed', [
                'audit_id' => $this->auditLog->id,
                'alerts_generated' => $alerts->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Security alert processing failed', [
                'audit_id' => $this->auditLog->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check for multiple failed operations from same IP
     */
    private function checkMultipleFailures($alerts): void
    {
        if ($this->auditLog->result !== 'failure') {
            return;
        }

        $cacheKey = "failed_attempts:{$this->auditLog->ip_address}";
        $attempts = Cache::increment($cacheKey);

        if ($attempts === 1) {
            Cache::expire($cacheKey, 3600);
        }

        if ($attempts >= 5) {
            $alert = SecurityAlert::firstOrCreate(
                [
                    'type' => 'multiple_failures',
                    'ip_address' => $this->auditLog->ip_address,
                    'status' => 'active',
                ],
                [
                    'severity' => 'high',
                    'message' => "Multiple failed operations from IP: {$this->auditLog->ip_address}",
                    'count' => $attempts,
                    'first_occurrence' => now()->subHour(),
                    'last_occurrence' => now(),
                    'metadata' => [
                        'operations' => [$this->auditLog->operation],
                        'user_agents' => [$this->auditLog->user_agent],
                    ],
                ]
            );

            $alert->increment('count');
            $alert->update(['last_occurrence' => now()]);

            $alerts->push($alert);
        }
    }

    /**
     * Check for high volume detokenization
     */
    private function checkHighVolumeDetokenize($alerts): void
    {
        if ($this->auditLog->operation !== 'detokenize') {
            return;
        }

        $hourKey = now()->format('Y-m-d-H');
        $cacheKey = "detokenize_count:{$this->auditLog->user_id}:{$hourKey}";
        $count = Cache::increment($cacheKey);

        if ($count === 1) {
            Cache::expire($cacheKey, 3600);
        }

        if ($count >= 100) {
            $alert = SecurityAlert::firstOrCreate(
                [
                    'type' => 'high_volume_detokenize',
                    'user_id' => $this->auditLog->user_id,
                    'status' => 'active',
                    'metadata->period' => $hourKey,
                ],
                [
                    'severity' => 'medium',
                    'message' => "High volume detokenize: {$count} operations in 1 hour by user {$this->auditLog->user_id}",
                    'count' => $count,
                    'first_occurrence' => now()->startOfHour(),
                    'last_occurrence' => now(),
                    'metadata' => [
                        'period' => $hourKey,
                        'vault_ids' => [$this->auditLog->vault_id],
                    ],
                ]
            );

            $alert->increment('count');
            $alerts->push($alert);
        }
    }

    /**
     * Check for off-hours access
     */
    private function checkOffHoursAccess($alerts): void
    {
        $hour = now()->hour;

        if ($hour >= 8 && $hour <= 18) {
            return;
        }

        if ($this->auditLog->operation === 'detokenize') {
            $alert = SecurityAlert::create([
                'type' => 'off_hours_access',
                'severity' => 'medium',
                'user_id' => $this->auditLog->user_id,
                'ip_address' => $this->auditLog->ip_address,
                'message' => "Off-hours detokenize operation at {$hour}:00 by user {$this->auditLog->user_id}",
                'count' => 1,
                'first_occurrence' => $this->auditLog->created_at,
                'last_occurrence' => $this->auditLog->created_at,
                'metadata' => [
                    'hour' => $hour,
                    'operation' => $this->auditLog->operation,
                    'vault_id' => $this->auditLog->vault_id,
                ],
            ]);

            $alerts->push($alert);
        }
    }

    /**
     * Check for suspicious patterns
     */
    private function checkSuspiciousPatterns($alerts): void
    {
        $recentIps = Cache::remember(
            "user_ips:{$this->auditLog->user_id}",
            300, // 5 minutes
            function () {
                return AuditLog::where('user_id', $this->auditLog->user_id)
                    ->where('created_at', '>=', now()->subMinutes(15))
                    ->distinct()
                    ->pluck('ip_address')
                    ->toArray();
            }
        );

        if (count($recentIps) >= 3) {
            $alert = SecurityAlert::create([
                'type' => 'multiple_ip_access',
                'severity' => 'high',
                'user_id' => $this->auditLog->user_id,
                'message' => "User accessing from multiple IPs: " . implode(', ', $recentIps),
                'count' => count($recentIps),
                'first_occurrence' => now()->subMinutes(15),
                'last_occurrence' => now(),
                'metadata' => [
                    'ip_addresses' => $recentIps,
                ],
            ]);

            $alerts->push($alert);
        }
    }

    /**
     * Process and send notifications for alerts
     */
    private function processAlerts($alerts): void
    {
        foreach ($alerts as $alert) {
            Log::info('Security alert generated', [
                'alert_id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
            ]);
            if ($alert->severity === 'high' || $alert->severity === 'critical') {
                Queue::pushOn('urgent_notifications', new SendSlackAlert($alert));
                Queue::pushOn('urgent_notifications', new SendEmailAlert($alert));
            } else {
                Queue::pushOn('notifications', new SendEmailAlert($alert));
            }

            $this->updateAlertCache($alert);
        }
    }

    /**
     * Update alert dashboard cache
     */
    private function updateAlertCache($alert): void
    {
        $today = now()->format('Y-m-d');
        $cacheKey = "security_alerts:{$today}";

        $todayAlerts = Cache::get($cacheKey, []);
        $todayAlerts[] = [
            'id' => $alert->id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'created_at' => $alert->created_at->toISOString(),
        ];

        Cache::put($cacheKey, $todayAlerts, now()->addDay());
    }
}
