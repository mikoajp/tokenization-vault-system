<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Commands\TokenizeCommand;
use App\Application\Commands\DetokenizeCommand;
use App\Application\Commands\BulkTokenizeCommand;
use App\Application\Commands\RevokeTokenCommand;
use App\Application\Services\TokenizationApplicationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\TokenizeRequest;
use App\Http\Requests\DetokenizeRequest;
use App\Http\Requests\BulkTokenizeRequest;
use App\Http\Requests\SearchTokensRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TokenizationController extends Controller
{
    public function __construct(
        private TokenizationApplicationService $tokenizationApplicationService
    ) {}

    /**
     * Tokenize sensitive data
     */
    public function tokenize(TokenizeRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $command = new TokenizeCommand(
                vaultId: $request->vault_id,
                data: $request->data,
                tokenType: $request->token_type ?? 'random',
                metadata: $request->metadata ?? [],
                expiresAt: $request->expires_at
            );

            $tokenDTO = $this->tokenizationApplicationService->tokenize($command);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'data' => [
                    'token_value' => $tokenDTO->tokenValue,
                    'token_id' => $tokenDTO->id,
                    'vault_id' => $tokenDTO->vaultId,
                    'token_type' => $tokenDTO->tokenType,
                    'expires_at' => $tokenDTO->expiresAt,
                    'metadata' => $tokenDTO->metadata,
                ],
                'message' => 'Data tokenized successfully',
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Tokenization failed', [
                'error' => $e->getMessage(),
                'vault_id' => $request->vault_id,
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Tokenization failed',
                    'code' => 'TOKENIZATION_ERROR',
                    'details' => $e->getMessage(),
                ],
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Detokenize token to get original data
     */
    public function detokenize(DetokenizeRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $command = new DetokenizeCommand(
                tokenValue: $request->token_value
            );

            $result = $this->tokenizationApplicationService->detokenize($command);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'data' => [
                    'token_value' => $result['token_value'],
                    'data' => $result['data'],
                ],
                'message' => 'Token detokenized successfully',
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Detokenization failed', [
                'error' => $e->getMessage(),
                'token_value' => $request->token_value,
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Detokenization failed',
                    'code' => 'DETOKENIZATION_ERROR',
                    'details' => $e->getMessage(),
                ],
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Search tokens by metadata
     */
    public function search(SearchTokensRequest $request): JsonResponse
    {
        try {
            $results = $this->tokenizationApplicationService->searchTokens(
                vaultId: $request->vault_id,
                searchCriteria: $request->criteria,
                limit: $request->limit ?? 100
            );

            return response()->json([
                'data' => $results,
                'message' => 'Token search completed successfully',
                'total_results' => count($results),
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Token search failed', [
                'error' => $e->getMessage(),
                'vault_id' => $request->vault_id,
                'criteria' => $request->criteria,
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Token search failed',
                    'code' => 'SEARCH_ERROR',
                    'details' => $e->getMessage(),
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Bulk tokenize multiple data items
     */
    public function bulkTokenize(BulkTokenizeRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $command = new BulkTokenizeCommand(
                vaultId: $request->vault_id,
                dataItems: $request->data_items,
                tokenType: $request->token_type ?? 'random',
                commonMetadata: $request->common_metadata ?? []
            );

            $results = $this->tokenizationApplicationService->bulkTokenize($command);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
            $errorCount = count($results) - $successCount;

            return response()->json([
                'data' => $results,
                'summary' => [
                    'total_items' => count($results),
                    'successful' => $successCount,
                    'failed' => $errorCount,
                    'success_rate' => round(($successCount / count($results)) * 100, 2),
                ],
                'message' => 'Bulk tokenization completed',
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], $errorCount > 0 ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Bulk tokenization failed', [
                'error' => $e->getMessage(),
                'vault_id' => $request->vault_id,
                'item_count' => count($request->data_items),
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Bulk tokenization failed',
                    'code' => 'BULK_TOKENIZATION_ERROR',
                    'details' => $e->getMessage(),
                ],
                'execution_time_ms' => $executionTime,
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Revoke a token
     */
    public function revokeToken(Request $request, string $vaultId, string $tokenId): JsonResponse
    {
        try {
            $reason = $request->input('reason');

            $command = new RevokeTokenCommand(
                tokenId: $tokenId,
                reason: $reason
            );

            $tokenDTO = $this->tokenizationApplicationService->revokeToken($command);

            return response()->json([
                'data' => [
                    'token_id' => $tokenDTO->id,
                    'status' => $tokenDTO->status,
                    'revoked_at' => now()->toISOString(),
                    'reason' => $reason,
                ],
                'message' => 'Token revoked successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Token revocation failed', [
                'error' => $e->getMessage(),
                'token_id' => $tokenId,
                'vault_id' => $vaultId,
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Token revocation failed',
                    'code' => 'REVOCATION_ERROR',
                    'details' => $e->getMessage(),
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get token statistics for a vault
     */
    public function getTokenStatistics(string $vaultId): JsonResponse
    {
        try {
            $statistics = $this->tokenizationApplicationService->getTokenStatistics($vaultId);

            return response()->json([
                'data' => $statistics,
                'message' => 'Token statistics retrieved successfully',
                'request_id' => request()->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get token statistics', [
                'error' => $e->getMessage(),
                'vault_id' => $vaultId,
                'request_id' => request()->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve token statistics',
                    'code' => 'STATISTICS_ERROR',
                    'details' => $e->getMessage(),
                ],
                'request_id' => request()->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
