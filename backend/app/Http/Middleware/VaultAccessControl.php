<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VaultAccessControl
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->attributes->get('api_key');

        if (!$apiKey) {
            return $this->accessDeniedResponse('API key authentication required');
        }

        // Check IP whitelist
        if (!$apiKey->isIpAllowed($request->ip())) {
            return $this->accessDeniedResponse('IP address not allowed');
        }

        // Get vault ID from request
        $vaultId = $this->extractVaultId($request);

        if ($vaultId && !$apiKey->hasVaultPermission($vaultId)) {
            return $this->accessDeniedResponse('Access denied to this vault');
        }

        // Check operation permission
        $operation = $this->determineOperation($request);

        if ($operation && !$apiKey->hasOperationPermission($operation)) {
            return $this->accessDeniedResponse("Operation '{$operation}' not allowed");
        }

        return $next($request);
    }

    private function extractVaultId(Request $request): ?string
    {
        // From route parameter
        if ($request->route('vault')) {
            return $request->route('vault');
        }

        // From request body
        return $request->input('vault_id');
    }

    private function determineOperation(Request $request): ?string
    {
        $route = $request->route()->getName();

        return match($route) {
            'api.tokenize' => 'tokenize',
            'api.detokenize' => 'detokenize',
            'api.search' => 'search',
            'api.bulk-tokenize' => 'bulk_tokenize',
            'api.revoke-token' => 'revoke',
            default => null,
        };
    }

    private function accessDeniedResponse(string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'code' => 'ACCESS_DENIED',
                'type' => 'authorization_error',
            ],
            'timestamp' => now()->toISOString(),
        ], Response::HTTP_FORBIDDEN);
    }
}
