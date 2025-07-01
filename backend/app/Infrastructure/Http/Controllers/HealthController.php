<?php

namespace App\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Vault;
use App\Infrastructure\Persistence\Eloquent\Models\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Models\SecurityAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'queue' => $this->checkQueue(),
                'storage' => $this->checkStorage(),
                'vaults' => $this->checkVaults(),
                'security' => $this->checkSecurity(),
            ]
        ];

        // Determine overall status
        $allHealthy = collect($health['checks'])->every(fn($check) => $check['status'] === 'healthy');
        $health['status'] = $allHealthy ? 'healthy' : 'degraded';

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    public function detailed(): JsonResponse
    {
        $details = [
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'timezone' => config('app.timezone'),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
            ],
            'database' => $this->getDatabaseDetails(),
            'vaults' => $this->getVaultDetails(),
            'security' => $this->getSecurityDetails(),
            'performance' => $this->getPerformanceMetrics(),
        ];

        return response()->json($details);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $responseTime = $this->measureResponseTime(fn() => DB::select('SELECT 1'));
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connection' => 'active'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            
            return [
                'status' => $value === 'test' ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size();
            $failed = DB::table('failed_jobs')->count();
            
            return [
                'status' => 'healthy',
                'pending_jobs' => $size,
                'failed_jobs' => $failed,
                'driver' => config('queue.default')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = \Storage::disk('local');
            $testFile = 'health_check_' . time() . '.txt';
            
            $disk->put($testFile, 'test');
            $content = $disk->get($testFile);
            $disk->delete($testFile);
            
            return [
                'status' => $content === 'test' ? 'healthy' : 'unhealthy',
                'writable' => true
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkVaults(): array
    {
        try {
            $total = Vault::count();
            $active = Vault::active()->count();
            $needingRotation = Vault::needingKeyRotation()->count();
            
            return [
                'status' => 'healthy',
                'total_vaults' => $total,
                'active_vaults' => $active,
                'vaults_needing_key_rotation' => $needingRotation
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkSecurity(): array
    {
        try {
            $openAlerts = SecurityAlert::open()->count();
            $criticalAlerts = SecurityAlert::critical()->open()->count();
            $recentFailedOperations = AuditLog::where('result', 'error')
                ->where('created_at', '>=', now()->subHour())
                ->count();
            
            $status = 'healthy';
            if ($criticalAlerts > 0) {
                $status = 'critical';
            } elseif ($openAlerts > 10 || $recentFailedOperations > 50) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'open_alerts' => $openAlerts,
                'critical_alerts' => $criticalAlerts,
                'recent_failed_operations' => $recentFailedOperations
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function getDatabaseDetails(): array
    {
        return [
            'driver' => config('database.default'),
            'tables' => [
                'vaults' => Vault::count(),
                'audit_logs' => AuditLog::count(),
                'security_alerts' => SecurityAlert::count(),
            ]
        ];
    }

    private function getVaultDetails(): array
    {
        return [
            'by_status' => Vault::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_data_type' => Vault::selectRaw('data_type, COUNT(*) as count')
                ->groupBy('data_type')
                ->pluck('count', 'data_type'),
        ];
    }

    private function getSecurityDetails(): array
    {
        return [
            'alerts_by_severity' => SecurityAlert::selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity'),
            'alerts_by_status' => SecurityAlert::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
        ];
    }

    private function getPerformanceMetrics(): array
    {
        $avgResponseTime = AuditLog::where('created_at', '>=', now()->subHour())
            ->avg('processing_time_ms');
            
        return [
            'avg_response_time_ms' => round($avgResponseTime, 2),
            'operations_last_hour' => AuditLog::where('created_at', '>=', now()->subHour())->count(),
            'success_rate_last_hour' => $this->calculateSuccessRate(),
        ];
    }

    private function calculateSuccessRate(): float
    {
        $total = AuditLog::where('created_at', '>=', now()->subHour())->count();
        
        if ($total === 0) {
            return 100.0;
        }
        
        $successful = AuditLog::where('created_at', '>=', now()->subHour())
            ->where('result', 'success')
            ->count();
            
        return round(($successful / $total) * 100, 2);
    }

    private function measureResponseTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        $end = microtime(true);
        
        return round(($end - $start) * 1000, 2);
    }
}