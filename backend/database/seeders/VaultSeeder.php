<?php

namespace Database\Seeders;

use App\Models\Vault;
use App\Models\VaultKey;
use Illuminate\Database\Seeder;

class VaultSeeder extends Seeder
{
    public function run(): void
    {
        $vaults = [
            [
                'name' => 'Primary Credit Card Vault',
                'description' => 'Main vault for storing credit card numbers',
                'data_type' => 'card',
                'status' => 'active',
                'max_tokens' => 1000000,
                'allowed_operations' => ['tokenize', 'detokenize'],
                'retention_days' => 2555, // 7 years
            ],
            [
                'name' => 'SSN Vault',
                'description' => 'Secure storage for Social Security Numbers',
                'data_type' => 'ssn',
                'status' => 'active',
                'max_tokens' => 500000,
                'allowed_operations' => ['tokenize', 'detokenize', 'search'],
                'retention_days' => 2555,
            ],
            [
                'name' => 'Bank Account Vault',
                'description' => 'Storage for bank account numbers',
                'data_type' => 'bank_account',
                'status' => 'active',
                'max_tokens' => 100000,
                'allowed_operations' => ['tokenize', 'detokenize'],
                'retention_days' => 1095, // 3 years
            ],
            [
                'name' => 'Development Vault',
                'description' => 'Vault for development and testing',
                'data_type' => 'custom',
                'status' => 'active',
                'max_tokens' => 10000,
                'allowed_operations' => ['tokenize', 'detokenize', 'search', 'bulk_tokenize'],
                'retention_days' => 365,
            ],
        ];

        foreach ($vaults as $vaultData) {
            $vault = Vault::create(array_merge($vaultData, [
                'encryption_algorithm' => 'AES-256-GCM',
                'encryption_key_reference' => 'hsm_key_' . \Illuminate\Support\Str::uuid(),
                'current_token_count' => 0,
                'access_restrictions' => [
                    'ip_whitelist' => ['192.168.1.0/24', '10.0.0.0/8'],
                    'time_restrictions' => null,
                ],
                'last_key_rotation' => now()->subDays(30),
                'key_rotation_interval_days' => 365,
            ]));

            VaultKey::create([
                'vault_id' => $vault->id,
                'key_version' => 'v1',
                'encrypted_key' => encrypt('sample_key_data_' . $vault->id),
                'key_hash' => hash('sha256', 'sample_key_data_' . $vault->id),
                'status' => 'active',
                'activated_at' => now()->subDays(30),
            ]);
        }

        Vault::factory(5)
            ->has(VaultKey::factory()->active())
            ->create();
    }
}
