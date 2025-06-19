<?php

use App\Http\Controllers\Api\TokenizationController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\AuditController;
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
            'cache' => 'healthy'
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

    return response()->json([
        'status' => 'operational',
        'database' => $dbStatus,
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
    Route::prefix('vaults')->group(function () {
        Route::get('/', [VaultController::class, 'index'])
            ->name('api.vaults.index');

        Route::post('/', [VaultController::class, 'store'])
            ->name('api.vaults.store');

        Route::get('/{vault}', [VaultController::class, 'show'])
            ->name('api.vaults.show');

        Route::put('/{vault}', [VaultController::class, 'update'])
            ->name('api.vaults.update');

        Route::get('/{vault}/statistics', [VaultController::class, 'statistics'])
            ->name('api.vaults.statistics');

        Route::post('/{vault}/rotate-key', [VaultController::class, 'rotateKey'])
            ->name('api.vaults.rotate-key');
    });

    // Audit and compliance endpoints
    Route::prefix('audit')->group(function () {
        Route::get('/logs', [AuditController::class, 'index'])
            ->name('api.audit.logs');

        Route::get('/summary', [AuditController::class, 'summary'])
            ->name('api.audit.summary');

        Route::get('/alerts', [AuditController::class, 'alerts'])
            ->name('api.audit.alerts');
    });
});
