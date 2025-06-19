<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;

class ApiKeySeeder extends Seeder
{
    public function run(): void
    {
        $apiKeys = [
            [
                'name' => 'Production Application',
                'vault_permissions' => ['*'],
                'operation_permissions' => ['tokenize', 'detokenize'],
                'rate_limit_per_hour' => 10000,
                'status' => 'active',
                'owner_type' => 'application',
                'owner_id' => 'prod_app_001',
                'description' => 'Main production application API key',
            ],
            [
                'name' => 'Analytics Service',
                'vault_permissions' => ['*'],
                'operation_permissions' => ['search'],
                'rate_limit_per_hour' => 5000,
                'status' => 'active',
                'owner_type' => 'service',
                'owner_id' => 'analytics_svc',
                'description' => 'Read-only access for analytics',
            ],
            [
                'name' => 'Development Environment',
                'vault_permissions' => ['Development Vault'],
                'operation_permissions' => ['*'],
                'rate_limit_per_hour' => 1000,
                'status' => 'active',
                'owner_type' => 'application',
                'owner_id' => 'dev_app_001',
                'description' => 'Full access to development vault only',
            ],
        ];

        foreach ($apiKeys as $keyData) {
            $keyValue = 'tvs_' . \Illuminate\Support\Str::random(32);

            ApiKey::create(array_merge($keyData, [
                'key_hash' => hash('sha256', $keyValue),
                'key_prefix' => substr($keyValue, 0, 8),
                'ip_whitelist' => null,
                'last_used_at' => null,
                'expires_at' => null,
                'usage_count' => 0,
            ]));
        }

        ApiKey::factory(10)->create();
    }
}
