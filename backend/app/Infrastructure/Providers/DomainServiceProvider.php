<?php

namespace App\Infrastructure\Providers;

use App\Domain\Vault\Repositories\VaultRepositoryInterface;
use App\Domain\Tokenization\Repositories\TokenRepositoryInterface;
use App\Infrastructure\Persistence\Repositories\EloquentVaultRepository;
use App\Infrastructure\Persistence\Repositories\EloquentTokenRepository;
use App\Domain\Vault\Services\VaultDomainService;
use App\Domain\Tokenization\Services\TokenizationDomainService;
use App\Application\Services\VaultApplicationService;
use App\Application\Services\TokenizationApplicationService;
use App\Application\Handlers\CreateVaultHandler;
use App\Application\Handlers\TokenizeHandler;
use App\Application\Handlers\DetokenizeHandler;
use App\Infrastructure\Services\EncryptionService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(
            VaultRepositoryInterface::class,
            EloquentVaultRepository::class
        );

        $this->app->bind(
            TokenRepositoryInterface::class,
            EloquentTokenRepository::class
        );

        // Infrastructure services
        $this->app->singleton(EncryptionService::class);

        // Domain services
        $this->app->singleton(VaultDomainService::class);
        $this->app->singleton(TokenizationDomainService::class);

        // Application handlers
        $this->app->singleton(CreateVaultHandler::class);
        $this->app->singleton(TokenizeHandler::class);
        $this->app->singleton(DetokenizeHandler::class);

        // Application services
        $this->app->singleton(VaultApplicationService::class);
        $this->app->singleton(TokenizationApplicationService::class);
    }

    public function boot(): void
    {
        //
    }
}