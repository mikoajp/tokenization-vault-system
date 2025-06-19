<?php

namespace Database\Factories;

use App\Models\VaultKey;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

class VaultKeyFactory extends Factory
{
    protected $model = VaultKey::class;

    public function definition(): array
    {
        return [
            'vault_id' => Vault::factory(),
            'key_version' => 'v' . $this->faker->numberBetween(1, 10),
            'encrypted_key' => encrypt($this->faker->sha256()),
            'key_hash' => $this->faker->sha256(),
            'status' => $this->faker->randomElement(['active', 'retired']),
            'activated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'retired_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+1 month'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'retired_at' => null,
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'retired',
            'retired_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
