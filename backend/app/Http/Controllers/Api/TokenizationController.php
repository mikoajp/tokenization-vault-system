<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TokenizeRequest;
use App\Http\Requests\DetokenizeRequest;
use App\Http\Requests\BulkTokenizeRequest;
use App\Http\Requests\SearchTokensRequest;
use App\Services\TokenizationService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TokenizationController extends Controller
{
    public function __construct(
        private TokenizationService $tokenizationService,
        private AuditService        $auditService
    )
    {
    }

    /**
     * Tokenize sensitive data
     */
    public function tokenize(TokenizeRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $result = $this->tokenizationService->tokenize(
                $request->vault_id,
                $request->data,
                $request->metadata ?? []
            );

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'tokenize',
                'vault_id' => $request->vault_id,
                'result' => 'success',
                'risk_level' => $this->determineRiskLevel($request),
                'pci_relevant' => true,
                'metadata' => [
                    'data_length' => strlen($request->data),
                    'format_preserving' => $request->format_preserving ?? false,
                    'execution_time_ms' => $executionTime,
                    'is_new_token' => $result['is_new'],
                ],
            ]);

            return response()->json([
                'data' => $result,
                'message' => $result['is_new'] ? 'Data tokenized successfully' : 'Existing token returned',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
                'performance' => [
                    'execution_time_ms' => $executionTime,
                ],
            ], $result['is_new'] ? Response::HTTP_CREATED : Response::HTTP_OK);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'tokenize',
                'vault_id' => $request->vault_id,
                'result' => 'failure',
                'risk_level' => 'high',
                'pci_relevant' => true,
                'metadata' => [
                    'error_message' => $e->getMessage(),
                    'execution_time_ms' => $executionTime,
                    'data_length' => strlen($request->data),
                ],
            ]);

            Log::error('Tokenization failed', [
                'vault_id' => $request->vault_id,
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Tokenization failed',
                    'code' => 'TOKENIZATION_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Detokenize to retrieve original data
     */
    public function detokenize(DetokenizeRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $originalData = $this->tokenizationService->detokenize(
                $request->vault_id,
                $request->token
            );

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'detokenize',
                'vault_id' => $request->vault_id,
                'result' => 'success',
                'risk_level' => 'high',
                'pci_relevant' => true,
                'metadata' => [
                    'token_prefix' => substr($request->token, 0, 8) . '***',
                    'execution_time_ms' => $executionTime,
                    'data_length' => strlen($originalData),
                ],
            ]);

            return response()->json([
                'data' => [
                    'original_data' => $originalData,
                    'vault_id' => $request->vault_id,
                    'token' => $request->token,
                ],
                'message' => 'Token detokenized successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
                'performance' => [
                    'execution_time_ms' => $executionTime,
                ],
            ]);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'detokenize',
                'vault_id' => $request->vault_id,
                'result' => 'failure',
                'risk_level' => 'critical',
                'pci_relevant' => true,
                'metadata' => [
                    'token_prefix' => substr($request->token, 0, 8) . '***',
                    'error_message' => $e->getMessage(),
                    'execution_time_ms' => $executionTime,
                ],
            ]);

            Log::error('Detokenization failed', [
                'vault_id' => $request->vault_id,
                'token_prefix' => substr($request->token, 0, 8) . '***',
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Detokenization failed',
                    'code' => 'DETOKENIZATION_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search tokens by criteria
     */
    public function search(SearchTokensRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $criteria = array_filter([
                'metadata' => $request->metadata,
                'token_type' => $request->token_type,
                'created_after' => $request->created_after,
                'created_before' => $request->created_before,
            ]);

            $results = $this->tokenizationService->search(
                $request->vault_id,
                $criteria,
                $request->limit ?? 100
            );

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'search',
                'vault_id' => $request->vault_id,
                'result' => 'success',
                'risk_level' => 'medium',
                'pci_relevant' => true,
                'metadata' => [
                    'criteria_count' => count($criteria),
                    'results_count' => count($results),
                    'limit' => $request->limit ?? 100,
                    'execution_time_ms' => $executionTime,
                ],
            ]);

            return response()->json([
                'data' => $results,
                'meta' => [
                    'count' => count($results),
                    'limit' => $request->limit ?? 100,
                    'criteria' => $criteria,
                ],
                'message' => 'Search completed successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
                'performance' => [
                    'execution_time_ms' => $executionTime,
                ],
            ]);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'search',
                'vault_id' => $request->vault_id,
                'result' => 'failure',
                'risk_level' => 'medium',
                'pci_relevant' => true,
                'metadata' => [
                    'error_message' => $e->getMessage(),
                    'execution_time_ms' => $executionTime,
                ],
            ]);

            Log::error('Search failed', [
                'vault_id' => $request->vault_id,
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Search failed',
                    'code' => 'SEARCH_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk tokenize multiple data items
     */
    public function bulkTokenize(BulkTokenizeRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $result = $this->tokenizationService->bulkTokenize(
                $request->vault_id,
                $request->data_items,
                $request->metadata ?? []
            );

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'bulk_tokenize',
                'vault_id' => $request->vault_id,
                'result' => $result['summary']['success'] === $result['summary']['total'] ? 'success' : 'partial',
                'risk_level' => count($request->data_items) > 1000 ? 'high' : 'medium',
                'pci_relevant' => true,
                'metadata' => [
                    'total_items' => $result['summary']['total'],
                    'successful_items' => $result['summary']['success'],
                    'failed_items' => $result['summary']['failed'],
                    'execution_time_ms' => $executionTime,
                    'items_per_second' => round($result['summary']['total'] / ($executionTime / 1000), 2),
                ],
            ]);

            return response()->json([
                'data' => $result['results'],
                'summary' => $result['summary'],
                'message' => sprintf(
                    'Bulk tokenization completed: %d/%d successful',
                    $result['summary']['success'],
                    $result['summary']['total']
                ),
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
                'performance' => [
                    'execution_time_ms' => $executionTime,
                    'items_per_second' => round($result['summary']['total'] / ($executionTime / 1000), 2),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'bulk_tokenize',
                'vault_id' => $request->vault_id,
                'result' => 'failure',
                'risk_level' => 'high',
                'pci_relevant' => true,
                'metadata' => [
                    'total_items' => count($request->data_items),
                    'error_message' => $e->getMessage(),
                    'execution_time_ms' => $executionTime,
                ],
            ]);

            Log::error('Bulk tokenization failed', [
                'vault_id' => $request->vault_id,
                'total_items' => count($request->data_items),
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Bulk tokenization failed',
                    'code' => 'BULK_TOKENIZATION_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Revoke a token
     */
    public function revokeToken(string $vaultId, string $tokenValue): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $this->tokenizationService->revokeToken($vaultId, $tokenValue);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'token_revoke',
                'vault_id' => $vaultId,
                'result' => 'success',
                'risk_level' => 'critical',
                'pci_relevant' => true,
                'metadata' => [
                    'token_prefix' => substr($tokenValue, 0, 8) . '***',
                    'execution_time_ms' => $executionTime,
                    'revoked_by' => auth()->id() ?? 'system',
                ],
            ]);

            return response()->json([
                'data' => [
                    'vault_id' => $vaultId,
                    'token' => $tokenValue,
                    'status' => 'revoked',
                ],
                'message' => 'Token revoked successfully',
                'request_id' => request()->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
                'performance' => [
                    'execution_time_ms' => $executionTime,
                ],
            ]);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->auditService->logEvent([
                'operation' => 'token_revoke',
                'vault_id' => $vaultId,
                'result' => 'failure',
                'risk_level' => 'critical',
                'pci_relevant' => true,
                'metadata' => [
                    'token_prefix' => substr($tokenValue, 0, 8) . '***',
                    'error_message' => $e->getMessage(),
                    'execution_time_ms' => $executionTime,
                ],
            ]);

            Log::error('Token revocation failed', [
                'vault_id' => $vaultId,
                'token_prefix' => substr($tokenValue, 0, 8) . '***',
                'error' => $e->getMessage(),
                'request_id' => request()->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Token revocation failed',
                    'code' => 'TOKEN_REVOCATION_ERROR',
                ],
                'request_id' => request()->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Determine risk level based on request data
     */
    private function determineRiskLevel(TokenizeRequest $request): string
    {
        $dataLength = strlen($request->data);

        if ($dataLength > 1000) {
            return 'high';
        }

        if ($request->format_preserving) {
            return 'high';
        }

        return 'medium';
    }
}
