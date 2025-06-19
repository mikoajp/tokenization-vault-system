<?php
// quick_api_key.php

require 'vendor/autoload.php';

try {
    $app = require_once 'bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    echo "🔑 Creating API Key without custom commands...\n\n";

    $keyValue = 'tvs_' . bin2hex(random_bytes(16));
    $uuid = (string) \Illuminate\Support\Str::uuid();

    // Sprawdź połączenie z bazą
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "✅ Database connection OK\n";

    // Utwórz tabelę jeśli nie istnieje
    $createTable = "
        CREATE TABLE IF NOT EXISTS api_keys (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            key_hash VARCHAR(255) NOT NULL UNIQUE,
            key_prefix VARCHAR(10) NOT NULL,
            vault_permissions JSON,
            operation_permissions JSON,
            ip_whitelist JSON,
            rate_limit_per_hour INTEGER DEFAULT 1000,
            status VARCHAR(20) DEFAULT 'active',
            last_used_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            usage_count INTEGER DEFAULT 0,
            owner_type VARCHAR(50),
            owner_id VARCHAR(100),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";

    \Illuminate\Support\Facades\DB::statement($createTable);
    echo "✅ Table api_keys ready\n";

    // Wstaw API key
    \Illuminate\Support\Facades\DB::table('api_keys')->insert([
        'id' => $uuid,
        'name' => 'Dashboard Admin Key',
        'key_hash' => hash('sha256', $keyValue),
        'key_prefix' => substr($keyValue, 0, 8),
        'vault_permissions' => json_encode(['*']),
        'operation_permissions' => json_encode(['*']),
        'ip_whitelist' => null,
        'rate_limit_per_hour' => 10000,
        'status' => 'active',
        'owner_type' => 'admin',
        'owner_id' => 'system',
        'description' => 'Quick setup admin key',
        'usage_count' => 0,
        'created_at' => now(),
        'updated_at' => now()
    ]);

    echo "✅ API Key created successfully!\n\n";
    echo "🔑 YOUR API KEY: " . $keyValue . "\n";
    echo "📋 Save this key securely!\n";
    echo "🌐 Use it to login at: http://localhost:3000\n\n";

    echo "🔍 Key details:\n";
    echo "- ID: " . $uuid . "\n";
    echo "- Prefix: " . substr($keyValue, 0, 8) . "\n";
    echo "- Full permissions: Yes\n";
    echo "- Rate limit: 10,000/hour\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
    echo "📂 File: " . $e->getFile() . "\n\n";

    echo "🔧 Try this manual approach:\n";
    echo "1. Open your database client\n";
    echo "2. Connect to your vault_system database\n";
    echo "3. Run this SQL:\n\n";

    $manualKey = 'tvs_' . bin2hex(random_bytes(16));
    $manualUuid = bin2hex(random_bytes(16));

    echo "INSERT INTO api_keys (id, name, key_hash, key_prefix, vault_permissions, operation_permissions, status, rate_limit_per_hour) VALUES (\n";
    echo "  '" . $manualUuid . "',\n";
    echo "  'Manual Admin Key',\n";
    echo "  '" . hash('sha256', $manualKey) . "',\n";
    echo "  '" . substr($manualKey, 0, 8) . "',\n";
    echo "  '[\"*\"]',\n";
    echo "  '[\"*\"]',\n";
    echo "  'active',\n";
    echo "  10000\n";
    echo ");\n\n";
    echo "🔑 Your manual API key would be: " . $manualKey . "\n";
}
?>
