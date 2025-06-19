<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vault;
use App\Models\Token;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * System health check
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => []
        ];

        // Database connectivity
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'response_time_ms' => $this->measureResponseTime(fn() => DB::select('SELECT 1')),
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
            ];
            $health['status'] = 'unhealthy';
        }

        // Cache connectivity
        try {
            Cache::put('health_check', time(), 10);
            $retrieved = Cache::get('health_check');
            $health['checks']['cache'] = [
                'status' => $retrieved ? 'healthy' : 'unhealthy',
                'response_time_ms' => $this->measureResponseTime(fn() => Cache::get('health_check')),
            ];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'unhealthy',
                'error' => 'Cache connection failed',
            ];
        }

        // System statistics
        try {
            $stats = [
                'vaults' => Vault::count(),
                'active_vaults' => Vault::where('status', 'active')->count(),
                'total_tokens' => Token::count(),
                'active_tokens' => Token::where('status', 'active')->count(),
                'audit_logs_24h' => AuditLog::where('created_at', '>=', now()->subDay())->count(),
            ];

            $health['checks']['system_stats'] = [
                'status' => 'healthy',
                'statistics' => $stats,
            ];
        } catch (\Exception $e) {
            $health['checks']['system_stats'] = [
                'status' => 'unhealthy',
                'error' => 'Failed to retrieve system statistics',
            ];
        }

        return response()->json($health, $health['status'] === 'healthy' ? 200 : 503);
    }

    /**
     * Detailed system status
     */
    public function status(): JsonResponse
    {
        try {
            $vaultStats = Vault::selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active,
                COUNT(CASE WHEN status = "inactive" THEN 1 END) as inactive,
                AVG(current_token_count) as avg_tokens,
                MAX(current_token_count) as max_tokens
            ')->first();

            $tokenStats = Token::selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active,
                COUNT(CASE WHEN status = "expired" THEN 1 END) as expired,
                COUNT(CASE WHEN status = "revoked" THEN 1 END) as revoked
            ')->first();

            $auditStats = AuditLog::selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN created_at >= NOW() - INTERVAL 1 DAY THEN 1 END) as last_24h,
                COUNT(CASE WHEN result = "failure" AND created_at >= NOW() - INTERVAL 1 DAY THEN 1 END) as failures_24h,
                COUNT(CASE WHEN risk_level = "high" AND created_at >= NOW() - INTERVAL 1 DAY THEN 1 END) as high_risk_24h
            ')->first();

            return response()->json([
                'status' => 'operational',
                'uptime' => $this->getUptime(),
                'vault_statistics' => $vaultStats,
                'token_statistics' => $tokenStats,
                'audit_statistics' => $auditStats,
                'system_info' => [
                    'php_version' => phpversion(),
                    'laravel_version' => app()->version(),
                    'timezone' => config('app.timezone'),
                    'debug_mode' => config('app.debug'),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to retrieve system status',
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    private function measureResponseTime(callable $callback): int
    {
        $start = microtime(true);
        $callback();
        return (int)((microtime(true) - $start) * 1000);
    }

    private function getUptime(): array
    {
        $uptime = file_exists('/proc/uptime') ?
            (float)explode(' ', file_get_contents('/proc/uptime'))[0] :
            null;

        return [
            'seconds' => $uptime,
            'human_readable' => $uptime ? gmdate('H:i:s', $uptime) : null,
        ];
    }
}
