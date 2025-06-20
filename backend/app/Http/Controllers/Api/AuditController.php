<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AuditController extends Controller
{
    public function __construct(private AuditService $auditService)
    {}

    /**
     * Get audit logs with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vault_id' => 'sometimes|uuid|exists:vaults,id',
            'operation' => 'sometimes|string',
            'result' => 'sometimes|in:success,failure,partial',
            'user_id' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'risk_level' => 'sometimes|in:low,medium,high,critical',
            'pci_relevant' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $cacheKey = $this->generateCacheKey('audit_logs', $validated);

            $result = Cache::remember($cacheKey, 300, function () use ($validated) {
                return $this->executeAuditQuery($validated);
            });

            return response()->json([
                'data' => AuditLogResource::collection($result['items']),
                'meta' => $result['meta'],
                'message' => 'Audit logs retrieved successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve audit logs', [
                'error' => $e->getMessage(),
                'filters' => $validated,
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve audit logs',
                    'code' => 'AUDIT_RETRIEVAL_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Execute audit logs query
     */
    private function executeAuditQuery(array $filters): array
    {
        $query = AuditLog::with('vault');

        if (!empty($filters['vault_id'])) {
            $query->where('vault_id', $filters['vault_id']);
        }

        if (!empty($filters['operation'])) {
            $query->where('operation', $filters['operation']);
        }

        if (!empty($filters['result'])) {
            $query->where('result', $filters['result']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [
                $filters['start_date'],
                $filters['end_date']
            ]);
        }

        if (!empty($filters['risk_level'])) {
            $query->where('risk_level', $filters['risk_level']);
        }

        if (isset($filters['pci_relevant'])) {
            $query->where('pci_relevant', $filters['pci_relevant']);
        }

        $auditLogs = $query->latest()->paginate($filters['per_page'] ?? 50);

        return [
            'items' => $auditLogs->items(),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
                'last_page' => $auditLogs->lastPage(),
            ],
        ];
    }

    /**
     * Get audit summary
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'vault_id' => 'sometimes|uuid|exists:vaults,id',
        ]);

        try {
            $startDate = Carbon::parse($validated['start_date'] ?? now()->subDays(30));
            $endDate = Carbon::parse($validated['end_date'] ?? now());
            $vaultId = $validated['vault_id'] ?? null;

            $summary = $this->auditService->getAuditSummary($startDate, $endDate, $vaultId);

            return response()->json([
                'data' => $summary,
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'vault_id' => $vaultId,
                ],
                'message' => 'Audit summary retrieved successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate audit summary', [
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to generate audit summary',
                    'code' => 'AUDIT_SUMMARY_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get security alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hours' => 'sometimes|integer|min:1|max:168',
        ]);

        try {
            $hours = $validated['hours'] ?? 24;

            $alerts = $this->auditService->getSecurityAlerts($hours);

            return response()->json([
                'data' => $alerts,
                'meta' => [
                    'alert_count' => $alerts->count(),
                    'period_hours' => $hours,
                    'generated_at' => now()->toISOString(),
                    'high_severity_count' => $alerts->where('severity', 'high')->count(),
                    'critical_severity_count' => $alerts->where('severity', 'critical')->count(),
                ],
                'message' => 'Security alerts retrieved successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve security alerts', [
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve security alerts',
                    'code' => 'SECURITY_ALERTS_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Manual audit log creation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operation' => 'required|string|max:100',
            'vault_id' => 'required|uuid|exists:vaults,id',
            'result' => 'required|in:success,failure,partial',
            'risk_level' => 'sometimes|in:low,medium,high,critical',
            'pci_relevant' => 'sometimes|boolean',
            'metadata' => 'sometimes|array',
            'description' => 'sometimes|string|max:500',
        ]);

        try {
            $auditId = $this->auditService->logEvent($validated);

            return response()->json([
                'data' => [
                    'audit_id' => $auditId,
                    'status' => 'queued',
                    'message' => 'Audit log queued for processing',
                ],
                'message' => 'Audit log created successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_ACCEPTED);

        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'data' => $validated,
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to create audit log',
                    'code' => 'AUDIT_CREATION_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get real-time metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $metrics = $this->auditService->getRealTimeMetrics();

            return response()->json([
                'data' => $metrics,
                'message' => 'Real-time metrics retrieved successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve real-time metrics', [
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve metrics',
                    'code' => 'METRICS_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Request compliance report generation
     */
    public function requestComplianceReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'report_type' => 'sometimes|in:pci_dss,sox,gdpr',
            'email' => 'sometimes|email',
        ]);

        try {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $reportType = $validated['report_type'] ?? 'pci_dss';

            $reportId = $this->auditService->generateComplianceReport($startDate, $endDate, $reportType);

            return response()->json([
                'data' => [
                    'report_id' => $reportId,
                    'status' => 'queued',
                    'estimated_completion' => now()->addMinutes(10)->toISOString(),
                    'report_type' => $reportType,
                ],
                'message' => 'Compliance report generation queued',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_ACCEPTED);

        } catch (\Exception $e) {
            Log::error('Failed to queue compliance report', [
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to queue compliance report',
                    'code' => 'COMPLIANCE_REPORT_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get immediate compliance data
     */
    public function complianceData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            $complianceData = $this->auditService->generateComplianceData($startDate, $endDate);

            return response()->json([
                'data' => $complianceData,
                'message' => 'Compliance data retrieved successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate compliance data', [
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to generate compliance data',
                    'code' => 'COMPLIANCE_DATA_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate cache key for requests
     */
    private function generateCacheKey(string $prefix, array $params): string
    {
        ksort($params);
        return $prefix . ':' . md5(serialize($params));
    }
}
