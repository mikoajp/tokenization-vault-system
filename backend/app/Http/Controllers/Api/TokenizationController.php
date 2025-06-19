<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TokenizeRequest;
use App\Http\Requests\DetokenizeRequest;
use App\Http\Requests\BulkTokenizeRequest;
use App\Http\Requests\SearchTokensRequest;
use App\Http\Resources\TokenResource;
use App\Services\TokenizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TokenizationController extends Controller
{
    public function __construct(private TokenizationService $tokenizationService)
    {}

    /**
     * Tokenize sensitive data
     */
    public function tokenize(TokenizeRequest $request): JsonResponse
    {
        $result = $this->tokenizationService->tokenize(
            $request->vault_id,
            $request->data,
            $request->metadata ?? []
        );

        return response()->json([
            'data' => $result,
            'message' => $result['is_new'] ? 'Data tokenized successfully' : 'Existing token returned',
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ], $result['is_new'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Detokenize to retrieve original data
     */
    public function detokenize(DetokenizeRequest $request): JsonResponse
    {
        $originalData = $this->tokenizationService->detokenize(
            $request->vault_id,
            $request->token
        );

        return response()->json([
            'data' => [
                'original_data' => $originalData,
                'vault_id' => $request->vault_id,
                'token' => $request->token,
            ],
            'message' => 'Token detokenized successfully',
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Search tokens by criteria
     */
    public function search(SearchTokensRequest $request): JsonResponse
    {
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
        ]);
    }

    /**
     * Bulk tokenize multiple data items
     */
    public function bulkTokenize(BulkTokenizeRequest $request): JsonResponse
    {
        $result = $this->tokenizationService->bulkTokenize(
            $request->vault_id,
            $request->data_items,
            $request->metadata ?? []
        );

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
        ], Response::HTTP_OK);
    }

    /**
     * Revoke a token
     */
    public function revokeToken(string $vaultId, string $tokenValue): JsonResponse
    {
        $this->tokenizationService->revokeToken($vaultId, $tokenValue);

        return response()->json([
            'data' => [
                'vault_id' => $vaultId,
                'token' => $tokenValue,
                'status' => 'revoked',
            ],
            'message' => 'Token revoked successfully',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
