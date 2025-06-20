<?php

use App\Http\Controllers\Api\TokenizationController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\ComplianceReportController;
use App\Http\Controllers\Api\SecurityAlertController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public health endpoints (no authentication required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
        'environment' => config('app.env'),
        'checks' => [
            'database' => 'healthy',
            'cache' => 'healthy',
            'queue' => 'healthy',
            'rabbitmq' => 'healthy'
        ]
    ]);
})->name('api.health');

Route::get('/health/status', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbStatus = 'healthy';
    } catch (\Exception $e) {
        $dbStatus = 'unhealthy';
    }

    try {
        \Illuminate\Support\Facades\Queue::size();
        $queueStatus = 'healthy';
    } catch (\Exception $e) {
        $queueStatus = 'unhealthy';
    }

    return response()->json([
        'status' => 'operational',
        'database' => $dbStatus,
        'queue' => $queueStatus,
        'timestamp' => now()->toISOString(),
    ]);
})->name('api.health.status');

// API Documentation
Route::get('/documentation', function () {
    return response()->json([
        'message' => 'Tokenization Vault System API',
        'version' => '1.0.0',
        'documentation_url' => config('app.url') . '/docs/api',
        'endpoints' => [
            'authentication' => 'Use API key in Authorization header: Bearer {api_key}',
            'tokenization' => '/api/v1/tokenize',
            'detokenization' => '/api/v1/detokenize',
            'search' => '/api/v1/search',
            'bulk_operations' => '/api/v1/bulk-tokenize',
            'vault_management' => '/api/v1/vaults',
            'audit_logs' => '/api/v1/audit',
            'compliance_reports' => '/api/v1/compliance/reports',
            'security_alerts' => '/api/v1/security/alerts',
        ],
        'timestamp' => now()->toISOString(),
    ]);
})->name('api.documentation');

// Protected API routes
Route::prefix('v1')->middleware([
    'api.auth',
    'api.rate_limit',
    'api.access_control',
    'api.request_id',
])->group(function () {

    // Tokenization endpoints
    Route::post('/tokenize', [TokenizationController::class, 'tokenize'])
        ->name('api.tokenize');

    Route::post('/detokenize', [TokenizationController::class, 'detokenize'])
        ->name('api.detokenize');

    Route::post('/search', [TokenizationController::class, 'search'])
        ->name('api.search');

    Route::post('/bulk-tokenize', [TokenizationController::class, 'bulkTokenize'])
        ->name('api.bulk-tokenize');

    Route::delete('/vaults/{vault}/tokens/{token}', [TokenizationController::class, 'revokeToken'])
        ->name('api.revoke-token');

    // Vault management endpoints
    Route::prefix('vaults')->name('api.vaults.')->group(function () {
        Route::get('/', [VaultController::class, 'index'])
            ->name('index');

        Route::post('/', [VaultController::class, 'store'])
            ->name('store');

        Route::get('/{vault}', [VaultController::class, 'show'])
            ->name('show');

        Route::put('/{vault}', [VaultController::class, 'update'])
            ->name('update');

        Route::get('/{vault}/statistics', [VaultController::class, 'statistics'])
            ->name('statistics');

        Route::post('/{vault}/rotate-key', [VaultController::class, 'rotateKey'])
            ->name('rotate-key');
    });

    // Audit and compliance endpoints
    Route::prefix('audit')->name('api.audit.')->group(function () {
        Route::get('/logs', [AuditController::class, 'index'])
            ->name('logs');

        Route::get('/summary', [AuditController::class, 'summary'])
            ->name('summary');

        Route::get('/alerts', [AuditController::class, 'alerts'])
            ->name('alerts');

        Route::post('/logs', [AuditController::class, 'store'])
            ->name('logs.store');

        Route::get('/metrics', [AuditController::class, 'metrics'])
            ->name('metrics');

        Route::post('/compliance/request', [AuditController::class, 'requestComplianceReport'])
            ->name('compliance.request');

        Route::get('/compliance/data', [AuditController::class, 'complianceData'])
            ->name('compliance.data');
    });

    // Compliance Reports endpoints
    Route::prefix('compliance')->name('api.compliance.')->group(function () {

        Route::get('/reports', [ComplianceReportController::class, 'index'])
            ->name('reports.index');

        Route::post('/reports', [ComplianceReportController::class, 'store'])
            ->name('reports.store');

        Route::get('/reports/{reportId}', [ComplianceReportController::class, 'show'])
            ->name('reports.show');

        Route::delete('/reports/{reportId}', [ComplianceReportController::class, 'destroy'])
            ->name('reports.destroy');

        Route::get('/reports/{reportId}/download', [ComplianceReportController::class, 'download'])
            ->name('reports.download');

        Route::post('/reports/{reportId}/share', [ComplianceReportController::class, 'share'])
            ->name('reports.share');

        Route::patch('/reports/{reportId}/extend', [ComplianceReportController::class, 'extend'])
            ->name('reports.extend');

        Route::get('/stats', [ComplianceReportController::class, 'stats'])
            ->name('stats');
    });

    // Security Alerts endpoints
    Route::prefix('security')->name('api.security.')->group(function () {

        Route::prefix('alerts')->name('alerts.')->group(function () {
            Route::get('/', [SecurityAlertController::class, 'index'])
                ->name('index');

            Route::get('/{alertId}', [SecurityAlertController::class, 'show'])
                ->name('show');

            Route::patch('/{alertId}/acknowledge', [SecurityAlertController::class, 'acknowledge'])
                ->name('acknowledge');

            Route::patch('/{alertId}/resolve', [SecurityAlertController::class, 'resolve'])
                ->name('resolve');

            Route::patch('/{alertId}/false-positive', [SecurityAlertController::class, 'markFalsePositive'])
                ->name('false-positive');

            Route::patch('/bulk/acknowledge', [SecurityAlertController::class, 'bulkAcknowledge'])
                ->name('bulk.acknowledge');

            Route::patch('/bulk/resolve', [SecurityAlertController::class, 'bulkResolve'])
                ->name('bulk.resolve');

            Route::get('/stats/summary', [SecurityAlertController::class, 'summary'])
                ->name('stats.summary');

            Route::get('/stats/trends', [SecurityAlertController::class, 'trends'])
                ->name('stats.trends');
        });
    });

    // System monitoring endpoints (admin only)
    Route::prefix('system')->name('api.system.')->middleware('admin')->group(function () {

        Route::get('/queues', function () {
            return response()->json([
                'queues' => [
                    'audit_logs' => \Illuminate\Support\Facades\Queue::size('audit_logs'),
                    'audit_logs_critical' => \Illuminate\Support\Facades\Queue::size('audit_logs_critical'),
                    'security_alerts' => \Illuminate\Support\Facades\Queue::size('security_alerts'),
                    'compliance_reports' => \Illuminate\Support\Facades\Queue::size('compliance_reports'),
                    'notifications' => \Illuminate\Support\Facades\Queue::size('notifications'),
                ],
                'failed_jobs' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
                'timestamp' => now()->toISOString(),
            ]);
        })->name('queues');

        Route::get('/metrics', function () {
            return response()->json([
                'database' => [
                    'audit_logs_count' => \App\Models\AuditLog::count(),
                    'security_alerts_count' => \App\Models\SecurityAlert::count(),
                    'compliance_reports_count' => \App\Models\ComplianceReport::count(),
                ],
                'recent_activity' => [
                    'audit_logs_24h' => \App\Models\AuditLog::recent(24)->count(),
                    'security_alerts_24h' => \App\Models\SecurityAlert::recent(24)->count(),
                    'active_alerts' => \App\Models\SecurityAlert::active()->count(),
                    'critical_alerts' => \App\Models\SecurityAlert::critical()->active()->count(),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        })->name('metrics');

        Route::get('/failed-jobs', function () {
            $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'data' => $failedJobs,
                'total' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
                'timestamp' => now()->toISOString(),
            ]);
        })->name('failed-jobs');

        Route::post('/failed-jobs/retry', function () {
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => 'all']);

            return response()->json([
                'message' => 'All failed jobs queued for retry',
                'timestamp' => now()->toISOString(),
            ]);
        })->name('failed-jobs.retry');
    });

    Route::prefix('webhooks')->name('api.webhooks.')->group(function () {

        Route::post('/security-alerts', function (\Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::info('Security alert webhook received', [
                'payload' => $request->all(),
                'source' => $request->header('X-Source', 'unknown'),
            ]);

            return response()->json(['received' => true]);
        })->name('security-alerts');

        Route::post('/compliance', function (\Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::info('Compliance webhook received', [
                'payload' => $request->all(),
                'source' => $request->header('X-Source', 'unknown'),
            ]);

            return response()->json(['received' => true]);
        })->name('compliance');
    });

    Route::prefix('utils')->name('api.utils.')->group(function () {

        Route::post('/generate-test-data', function () {
            if (!app()->environment('local', 'development')) {
                return response()->json(['error' => 'Only available in development'], 403);
            }

            \App\Models\AuditLog::factory(50)->create();

            \App\Models\SecurityAlert::factory(10)->create();

            return response()->json([
                'message' => 'Test data generated successfully',
                'generated' => [
                    'audit_logs' => 50,
                    'security_alerts' => 10,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        })->name('generate-test-data');

        Route::post('/cache/clear', function () {
            \Illuminate\Support\Facades\Cache::flush();

            return response()->json([
                'message' => 'Cache cleared successfully',
                'timestamp' => now()->toISOString(),
            ]);
        })->name('cache.clear');

        Route::get('/health/dependencies', function () {
            $health = [
                'database' => 'healthy',
                'redis' => 'healthy',
                'rabbitmq' => 'healthy',
                'storage' => 'healthy',
            ];

            try {
                \Illuminate\Support\Facades\DB::connection()->getPdo();
            } catch (\Exception $e) {
                $health['database'] = 'unhealthy';
            }

            try {
                \Illuminate\Support\Facades\Redis::ping();
            } catch (\Exception $e) {
                $health['redis'] = 'unhealthy';
            }

            try {
                \Illuminate\Support\Facades\Queue::size();
            } catch (\Exception $e) {
                $health['rabbitmq'] = 'unhealthy';
            }

            try {
                \Illuminate\Support\Facades\Storage::disk('local')->exists('test');
            } catch (\Exception $e) {
                $health['storage'] = 'unhealthy';
            }

            $overallStatus = in_array('unhealthy', $health) ? 'degraded' : 'healthy';

            return response()->json([
                'status' => $overallStatus,
                'dependencies' => $health,
                'timestamp' => now()->toISOString(),
            ]);
        })->name('health.dependencies');
    });
});
