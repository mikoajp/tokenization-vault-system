<?php

namespace Database\Factories;

use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

class VaultFactory extends Factory
{
    protected $model = Vault::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Vault',
            'description' => $this->faker->sentence(),
            'data_type' => $this->faker->randomElement(['card', 'ssn', 'bank_account', 'custom']),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'encryption_algorithm' => 'AES-256-GCM',
            'encryption_key_reference' => 'vault_key_' . $this->faker->uuid(),
            'max_tokens' => $this->faker->numberBetween(10000, 1000000),
            'current_token_count' => $this->faker->numberBetween(0, 1000),
            'allowed_operations' => $this->faker->randomElements(
                ['tokenize', 'detokenize', 'search', 'bulk_tokenize'],
                $this->faker->numberBetween(2, 4)
            ),
            'access_restrictions' => [
                'ip_whitelist' => $this->faker->randomElements([
                    '192.168.1.0/24',
                    '10.0.0.0/8',
                    '172.16.0.0/12'
                ], $this->faker->numberBetween(1, 2)),
                'time_restrictions' => [
                    'start_hour' => 8,
                    'end_hour' => 18
                ]
            ],
            'retention_days' => $this->faker->randomElement([365, 1095, 2555]),
            'last_key_rotation' => $this->faker->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'key_rotation_interval_days' => $this->faker->randomElement([90, 180, 365]),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function cardVault(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'card',
            'name' => 'Credit Card Vault',
            'allowed_operations' => ['tokenize', 'detokenize'],
        ]);
    }

    public function ssnVault(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'ssn',
            'name' => 'SSN Vault',
            'allowed_operations' => ['tokenize', 'detokenize', 'search'],
        ]);
    }
}
