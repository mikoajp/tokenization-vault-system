<?php

namespace Database\Factories;

use App\Models\Token;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

class TokenFactory extends Factory
{
    protected $model = Token::class;

    public function definition(): array
    {
        $originalData = $this->generateOriginalData();

        return [
            'vault_id' => Vault::factory(),
            'token_value' => 'tok_' . $this->faker->regexify('[A-Za-z0-9]{32}'),
            'encrypted_data' => encrypt($originalData),
            'data_hash' => hash('sha256', $originalData),
            'format_preserved_token' => $this->generateFormatPreservedToken($originalData),
            'token_type' => $this->faker->randomElement(['random', 'format_preserving', 'sequential']),
            'metadata' => [
                'created_by' => $this->faker->uuid(),
                'source_system' => $this->faker->randomElement(['web', 'api', 'batch']),
                'category' => $this->faker->randomElement(['payment', 'identity', 'custom']),
            ],
            'original_data_hash' => hash('sha256', $originalData ?? $this->faker->uuid()),
            'checksum' => $this->faker->sha256(),
            'usage_count' => $this->faker->numberBetween(0, 100),
            'last_used_at' => $this->faker->optional(0.6)->dateTimeBetween('-1 month', 'now'),
            'expires_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+1 year'),
            'key_version' => 'v1',
            'status' => $this->faker->randomElement(['active', 'expired', 'revoked']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => $this->faker->dateTimeBetween('+1 month', '+1 year'),
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(function (array $attributes) {
            $cardNumber = $this->faker->creditCardNumber();
            return [
                'encrypted_data' => encrypt($cardNumber),
                'data_hash' => hash('sha256', $cardNumber),
                'format_preserved_token' => $this->generateFormatPreservedToken($cardNumber),
                'token_type' => 'format_preserving',
                'metadata' => [
                    'card_type' => $this->faker->creditCardType(),
                    'last_four' => substr($cardNumber, -4),
                    'source_system' => 'payment_gateway',
                ],
            ];
        });
    }

    public function ssn(): static
    {
        return $this->state(function (array $attributes) {
            $ssn = $this->faker->ssn();
            return [
                'encrypted_data' => encrypt($ssn),
                'data_hash' => hash('sha256', $ssn),
                'format_preserved_token' => $this->generateFormatPreservedToken($ssn),
                'token_type' => 'format_preserving',
                'metadata' => [
                    'data_type' => 'ssn',
                    'source_system' => 'hr_system',
                ],
            ];
        });
    }

    private function generateOriginalData(): string
    {
        $types = ['credit_card', 'ssn', 'bank_account', 'custom'];
        $type = $this->faker->randomElement($types);

        return match($type) {
            'credit_card' => $this->faker->creditCardNumber(),
            'ssn' => $this->faker->ssn(),
            'bank_account' => $this->faker->bankAccountNumber(),
            'custom' => $this->faker->regexify('[A-Z0-9]{10,20}'),
        };
    }


    private function generateFormatPreservedToken(string $originalData): string
    {
        // Simple format preservation - replace digits with random digits
        return preg_replace_callback('/\d/', function($matches) {
            return random_int(0, 9);
        }, $originalData);
    }
}
