<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Commands\CreateVaultCommand;
use App\Application\Commands\UpdateVaultCommand;
use App\Application\Commands\RotateVaultKeyCommand;
use App\Application\Services\VaultApplicationService;
use App\Http\Requests\CreateVaultRequest;
use App\Http\Resources\VaultResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VaultController extends Controller
{
    public function __construct(
        private VaultApplicationService $vaultApplicationService
    ) {}

    /**
     * Get all vaults (with pagination)
     */
    public function index(Request $request): JsonResponse
    {
        $vaults = $this->vaultApplicationService->getVaults(
            perPage: $request->get('per_page', 15),
            dataType: $request->get('data_type'),
            status: $request->get('status'),
            search: $request->get('search')
        );

        return response()->json([
            'data' => VaultResource::collection($vaults->items()),
            'meta' => [
                'current_page' => $vaults->currentPage(),
                'per_page' => $vaults->perPage(),
                'total' => $vaults->total(),
                'last_page' => $vaults->lastPage(),
            ],
            'message' => 'Vaults retrieved successfully',
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Create a new vault
     */
    public function store(CreateVaultRequest $request): JsonResponse
    {
        $command = new CreateVaultCommand(
            name: $request->name,
            description: $request->description,
            dataType: $request->data_type,
            encryptionAlgorithm: $request->encryption_algorithm ?? 'AES-256-GCM',
            allowedOperations: $request->allowed_operations ?? ['tokenize', 'detokenize'],
            accessRestrictions: $request->access_restrictions,
            maxTokens: $request->max_tokens ?? 1000000,
            retentionDays: $request->retention_days ?? 2555,
            keyRotationIntervalDays: $request->key_rotation_interval_days ?? 365
        );

        $vaultDTO = $this->vaultApplicationService->createVault($command);

        return response()->json([
            'data' => new VaultResource((object) $vaultDTO->toArray()),
            'message' => 'Vault created successfully',
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Get specific vault details
     */
    public function show(string $id): JsonResponse
    {
        $vaultDTO = $this->vaultApplicationService->getVault($id);

        return response()->json([
            'data' => new VaultResource((object) $vaultDTO->toArray()),
            'message' => 'Vault retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Update vault configuration
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $updateData = $request->validate([
            'name' => 'sometimes|string|min:3|max:100',
            'description' => 'sometimes|nullable|string|max:500',
            'status' => 'sometimes|in:active,inactive,archived',
            'max_tokens' => 'sometimes|integer|min:1000|max:10000000',
            'allowed_operations' => 'sometimes|array',
            'allowed_operations.*' => 'in:tokenize,detokenize,search,bulk_tokenize,bulk_detokenize',
            'access_restrictions' => 'sometimes|nullable|array',
            'retention_days' => 'sometimes|integer|min:30|max:3650',
        ]);

        $command = new UpdateVaultCommand(
            vaultId: $id,
            name: $updateData['name'] ?? null,
            description: $updateData['description'] ?? null,
            status: $updateData['status'] ?? null,
            maxTokens: $updateData['max_tokens'] ?? null,
            allowedOperations: $updateData['allowed_operations'] ?? null,
            accessRestrictions: $updateData['access_restrictions'] ?? null,
            retentionDays: $updateData['retention_days'] ?? null
        );

        $vaultDTO = $this->vaultApplicationService->updateVault($command);

        return response()->json([
            'data' => new VaultResource((object) $vaultDTO->toArray()),
            'message' => 'Vault updated successfully',
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get vault statistics
     */
    public function statistics(string $id): JsonResponse
    {
        $statistics = $this->vaultApplicationService->getVaultStatistics($id);

        return response()->json([
            'data' => $statistics,
            'message' => 'Vault statistics retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Rotate vault encryption key
     */
    public function rotateKey(string $id): JsonResponse
    {
        $command = new RotateVaultKeyCommand($id);
        $vaultDTO = $this->vaultApplicationService->rotateVaultKey($command);

        return response()->json([
            'data' => [
                'vault_id' => $vaultDTO->id,
                'new_key_rotation_date' => $vaultDTO->lastKeyRotation,
                'status' => 'success',
            ],
            'message' => 'Vault key rotated successfully',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}