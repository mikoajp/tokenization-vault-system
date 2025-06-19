<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Vault;
use App\Models\Token;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        $operations = [
            'tokenize', 'detokenize', 'search', 'bulk_tokenize', 'bulk_detokenize',
            'vault_create', 'vault_update', 'vault_delete',
            'key_rotation', 'token_revoke', 'token_expire'
        ];

        return [
            'vault_id' => $this->faker->optional(0.8)->randomElement(Vault::pluck('id')),
            'token_id' => $this->faker->optional(0.6)->randomElement(Token::pluck('id')),
            'operation' => $this->faker->randomElement($operations),
            'result' => $this->faker->randomElement(['success', 'failure', 'partial']),
            'error_message' => $this->faker->optional(0.2)->sentence(),
            'user_id' => $this->faker->uuid(),
            'api_key_id' => $this->faker->optional(0.7)->uuid(),
            'session_id' => $this->faker->optional(0.5)->uuid(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'request_id' => $this->faker->uuid(),
            'request_metadata' => [
                'endpoint' => '/api/v1/' . $this->faker->randomElement(['tokenize', 'detokenize', 'search']),
                'method' => $this->faker->randomElement(['POST', 'GET', 'PUT']),
                'content_length' => $this->faker->numberBetween(100, 5000),
            ],
            'response_metadata' => [
                'status_code' => $this->faker->randomElement([200, 201, 400, 401, 403, 500]),
                'response_size' => $this->faker->numberBetween(50, 1000),
                'cache_hit' => $this->faker->boolean(),
            ],
            'processing_time_ms' => $this->faker->numberBetween(10, 5000),
            'risk_level' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'pci_relevant' => $this->faker->boolean(80), // 80% PCI relevant
            'compliance_reference' => $this->faker->optional(0.3)->regexify('AUDIT-[0-9]{8}'),
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'success',
            'error_message' => null,
        ]);
    }

    public function failure(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'failure',
            'error_message' => $this->faker->sentence(),
        ]);
    }

    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_level' => 'high',
            'operation' => $this->faker->randomElement(['detokenize', 'key_rotation', 'vault_delete']),
        ]);
    }

    public function pciRelevant(): static
    {
        return $this->state(fn (array $attributes) => [
            'pci_relevant' => true,
        ]);
    }
}
