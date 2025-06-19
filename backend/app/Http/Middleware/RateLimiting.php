<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class RateLimiting
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->attributes->get('api_key');

        if (!$apiKey) {
            return $next($request);
        }

        $key = 'rate_limit:' . $apiKey->id;
        $limit = $apiKey->rate_limit_per_hour;
        $window = 3600; // 1 hour in seconds

        $current = Cache::get($key, 0);

        if ($current >= $limit) {
            return response()->json([
                'error' => [
                    'message' => 'Rate limit exceeded',
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'type' => 'rate_limit_error',
                    'limit' => $limit,
                    'window_seconds' => $window,
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Increment counter
        Cache::put($key, $current + 1, $window);

        // Add rate limit headers
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $limit - $current - 1));
        $response->headers->set('X-RateLimit-Reset', now()->addSeconds($window)->timestamp);

        return $response;
    }
}
