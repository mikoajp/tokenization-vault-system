<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateVaultRequest;
use App\Http\Resources\VaultResource;
use App\Models\Vault;
use App\Services\VaultManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VaultController extends Controller
{
    public function __construct(private VaultManager $vaultManager)
    {}

    /**
     * Get all vaults (with pagination)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vault::query();

        if ($request->has('data_type')) {
            $query->where('data_type', $request->data_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $vaults = $query->paginate($request->per_page ?? 15);

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
        $vault = $this->vaultManager->createVault($request->validated());

        return response()->json([
            'data' => new VaultResource($vault),
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
        $vault = Vault::findOrFail($id);

        return response()->json([
            'data' => new VaultResource($vault),
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

        $vault = $this->vaultManager->updateVault($id, $updateData);

        return response()->json([
            'data' => new VaultResource($vault),
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
        $statistics = $this->vaultManager->getVaultStatistics($id);

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
        $newKey = $this->vaultManager->rotateVaultKey($id);

        return response()->json([
            'data' => [
                'vault_id' => $id,
                'new_key_version' => $newKey->key_version,
                'activated_at' => $newKey->activated_at,
            ],
            'message' => 'Vault key rotated successfully',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
