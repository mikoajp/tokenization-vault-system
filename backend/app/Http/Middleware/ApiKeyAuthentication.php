<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiKeyAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return $this->unauthorizedResponse('API key is required');
        }

        $keyRecord = $this->validateApiKey($apiKey);

        if (!$keyRecord) {
            return $this->unauthorizedResponse('Invalid API key');
        }

        if (!$keyRecord->isActive()) {
            return $this->unauthorizedResponse('API key is inactive or expired');
        }

        $request->attributes->set('api_key', $keyRecord);
        $request->headers->set('X-API-Key-ID', $keyRecord->id);

        $keyRecord->recordUsage();

        return $next($request);
    }

    private function extractApiKey(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return $request->header('X-API-Key');
    }

    private function validateApiKey(string $apiKey): ?ApiKey
    {
        $keyHash = hash('sha256', $apiKey);

        return ApiKey::where('key_hash', $keyHash)
            ->where('status', 'active')
            ->first();
    }

    private function unauthorizedResponse(string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'code' => 'UNAUTHORIZED',
                'type' => 'authentication_error',
            ],
            'timestamp' => now()->toISOString(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
