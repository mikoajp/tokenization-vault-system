<?php

namespace Database\Factories;

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        $keyValue = 'tvs_' . $this->faker->regexify('[A-Za-z0-9]{32}');

        return [
            'name' => $this->faker->words(3, true) . ' API Key',
            'key_hash' => hash('sha256', $keyValue),
            'key_prefix' => substr($keyValue, 0, 8),
            'vault_permissions' => $this->faker->randomElements([
                'vault_1', 'vault_2', 'vault_3', '*'
            ], $this->faker->numberBetween(1, 3)),
            'operation_permissions' => $this->faker->randomElements([
                'tokenize', 'detokenize', 'search', 'bulk_tokenize', '*'
            ], $this->faker->numberBetween(2, 4)),
            'ip_whitelist' => $this->faker->optional(0.6)->randomElements([
                '192.168.1.0/24', '10.0.0.0/8', '172.16.0.0/12'
            ], $this->faker->numberBetween(1, 2)),
            'rate_limit_per_hour' => $this->faker->randomElement([100, 500, 1000, 5000]),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'last_used_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'expires_at' => $this->faker->optional(0.4)->dateTimeBetween('+1 month', '+1 year'),
            'usage_count' => $this->faker->numberBetween(0, 10000),
            'owner_type' => $this->faker->randomElement(['user', 'service', 'application']),
            'owner_id' => $this->faker->uuid(),
            'description' => $this->faker->optional(0.8)->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function fullAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'vault_permissions' => ['*'],
            'operation_permissions' => ['*'],
            'rate_limit_per_hour' => 10000,
        ]);
    }

    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'operation_permissions' => ['search'],
            'rate_limit_per_hour' => 500,
        ]);
    }
}
