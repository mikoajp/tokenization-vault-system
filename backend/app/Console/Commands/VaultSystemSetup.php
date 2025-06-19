<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VaultSystemSetup extends Command
{
    protected $signature = 'vault:setup
                            {--fresh : Drop all tables and start fresh}
                            {--seed : Seed with sample data}
                            {--force : Force setup without confirmation}';

    protected $description = 'Setup the Tokenization Vault System database and initial data';

    public function handle(): int
    {
        $this->info('🏗️  Setting up Tokenization Vault System...');

        if ($this->option('fresh') && !$this->option('force')) {
            if (!$this->confirm('This will drop all existing tables. Are you sure?')) {
                $this->info('Setup cancelled.');
                return 1;
            }
        }

        try {
            // Step 1: Setup database
            $this->setupDatabase();

            // Step 2: Run migrations
            $this->runMigrations();

            // Step 3: Seed data if requested
            if ($this->option('seed')) {
                $this->seedDatabase();
            }

            // Step 4: Setup vault keys
            $this->setupVaultKeys();

            // Step 5: Create default API key
            $this->createDefaultApiKey();

            $this->info('✅ Vault System setup completed successfully!');
            $this->displaySystemInfo();

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Setup failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function setupDatabase(): void
    {
        $this->info('📊 Setting up database...');

        if ($this->option('fresh')) {
            $this->warn('Dropping all tables...');

            // Get all table names
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

            DB::statement('PRAGMA foreign_keys = OFF');

            foreach ($tables as $table) {
                Schema::dropIfExists($table->name);
            }

            DB::statement('PRAGMA foreign_keys = ON');

            $this->info('All tables dropped.');
        }
    }

    private function runMigrations(): void
    {
        $this->info('🚀 Running migrations...');

        Artisan::call('migrate', [
            '--force' => true
        ]);

        $this->info('Migrations completed.');
    }

    private function seedDatabase(): void
    {
        $this->info('🌱 Seeding database with sample data...');

        Artisan::call('db:seed', [
            '--force' => true
        ]);

        $this->info('Database seeded successfully.');
    }

    private function setupVaultKeys(): void
    {
        $this->info('🔐 Setting up vault encryption keys...');

        // This would integrate with your actual key management system
        // For demo purposes, we'll just log the setup

        $this->info('Vault keys configured.');
    }

    private function createDefaultApiKey(): void
    {
        $this->info('🔑 Creating default API key...');

        $keyValue = 'tvs_' . \Illuminate\Support\Str::random(32);

        $apiKey = \App\Models\ApiKey::create([
            'name' => 'Default Admin Key',
            'key_hash' => hash('sha256', $keyValue),
            'key_prefix' => substr($keyValue, 0, 8),
            'vault_permissions' => ['*'],
            'operation_permissions' => ['*'],
            'rate_limit_per_hour' => 10000,
            'status' => 'active',
            'owner_type' => 'admin',
            'owner_id' => 'system',
            'description' => 'Default administrative API key created during setup',
        ]);

        $this->info("Default API Key created: {$keyValue}");
        $this->warn('⚠️  Please save this key securely - it won\'t be shown again!');
    }

    private function displaySystemInfo(): void
    {
        $this->info('');
        $this->info('📈 System Status:');

        $vaultCount = \App\Models\Vault::count();
        $tokenCount = \App\Models\Token::count();
        $auditCount = \App\Models\AuditLog::count();
        $apiKeyCount = \App\Models\ApiKey::count();

        $this->table(
            ['Component', 'Count', 'Status'],
            [
                ['Vaults', $vaultCount, '✅ Ready'],
                ['Tokens', $tokenCount, '✅ Ready'],
                ['Audit Logs', $auditCount, '✅ Ready'],
                ['API Keys', $apiKeyCount, '✅ Ready'],
            ]
        );

        $this->info('');
        $this->info('🚀 Next Steps:');
        $this->info('1. Start the Laravel server: php artisan serve');
        $this->info('2. Access the dashboard: http://localhost:8000');
        $this->info('3. Review API documentation: /api/documentation');
        $this->info('4. Monitor audit logs: php artisan vault:audit-summary');
    }
}
