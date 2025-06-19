<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Vault;
use App\Models\Token;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $vaultIds = Vault::pluck('id')->toArray();
        $tokenIds = Token::pluck('id')->toArray();

        for ($i = 0; $i < 1000; $i++) {
            AuditLog::factory()->create([
                'vault_id' => fake()->randomElement(array_merge($vaultIds, [null])),
                'token_id' => fake()->randomElement(array_merge($tokenIds, [null, null])),
                'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
            ]);
        }

        AuditLog::factory()
            ->count(50)
            ->highRisk()
            ->pciRelevant()
            ->create([
                'vault_id' => fake()->randomElement($vaultIds),
                'created_at' => fake()->dateTimeBetween('-7 days', 'now'),
            ]);
    }
}
