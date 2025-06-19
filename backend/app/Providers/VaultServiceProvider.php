<?php

namespace App\Providers;

use App\Services\EncryptionService;
use App\Services\TokenizationService;
use App\Services\VaultManager;
use App\Services\AuditService;
use Illuminate\Support\ServiceProvider;

class VaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EncryptionService::class);
        $this->app->singleton(AuditService::class);

        $this->app->singleton(TokenizationService::class, function ($app) {
            return new TokenizationService(
                $app->make(EncryptionService::class),
                $app->make(AuditService::class)
            );
        });

        $this->app->singleton(VaultManager::class, function ($app) {
            return new VaultManager(
                $app->make(EncryptionService::class),
                $app->make(AuditService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/vault.php' => config_path('vault.php'),
            __DIR__.'/../../config/tokenization.php' => config_path('tokenization.php'),
            __DIR__.'/../../config/api.php' => config_path('api.php'),
            __DIR__.'/../../config/audit.php' => config_path('audit.php'),
        ], 'vault-config');
    }
}
