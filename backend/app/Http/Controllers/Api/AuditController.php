<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(private AuditService $auditService)
    {}

    /**
     * Get audit logs with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
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

        $query = AuditLog::with('vault');

        // Apply filters
        if ($request->has('vault_id')) {
            $query->where('vault_id', $request->vault_id);
        }

        if ($request->has('operation')) {
            $query->where('operation', $request->operation);
        }

        if ($request->has('result')) {
            $query->where('result', $request->result);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        if ($request->has('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->has('pci_relevant')) {
            $query->where('pci_relevant', $request->boolean('pci_relevant'));
        }

        $auditLogs = $query->latest()->paginate($request->per_page ?? 50);

        return response()->json([
            'data' => AuditLogResource::collection($auditLogs->items()),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
                'last_page' => $auditLogs->lastPage(),
            ],
            'message' => 'Audit logs retrieved successfully',
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get audit summary
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'vault_id' => 'sometimes|uuid|exists:vaults,id',
        ]);

        $startDate = Carbon::parse($request->start_date ?? now()->subDays(30));
        $endDate = Carbon::parse($request->end_date ?? now());
        $vaultId = $request->vault_id;

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
    }

    /**
     * Get security alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $request->validate([
            'hours' => 'sometimes|integer|min:1|max:168', // Max 1 week
        ]);

        $hours = $request->hours ?? 24;
        $alerts = $this->auditService->getSecurityAlerts($hours);

        return response()->json([
            'data' => $alerts,
            'meta' => [
                'alert_count' => $alerts->count(),
                'period_hours' => $hours,
                'generated_at' => now()->toISOString(),
            ],
            'message' => 'Security alerts retrieved successfully',
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
