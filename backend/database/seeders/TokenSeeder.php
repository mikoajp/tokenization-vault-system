<?php

namespace Database\Seeders;

use App\Models\Token;
use App\Models\Vault;
use Illuminate\Database\Seeder;

class TokenSeeder extends Seeder
{
    public function run(): void
    {
        $vaults = Vault::all();

        foreach ($vaults as $vault) {
            $tokenCount = match($vault->data_type) {
                'card' => 50,
                'ssn' => 30,
                'bank_account' => 20,
                'custom' => 40,
                default => 25,
            };

            $factory = Token::factory()->count($tokenCount);

            if ($vault->data_type === 'card') {
                $factory->creditCard();
            } elseif ($vault->data_type === 'ssn') {
                $factory->ssn();
            }

            $tokens = $factory->create(['vault_id' => $vault->id]);

            $vault->update(['current_token_count' => $tokens->count()]);
        }
    }
}
