<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecurityAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SecurityAlertController extends Controller
{
    /**
     * Get list of security alerts with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:active,acknowledged,resolved,false_positive',
            'severity' => 'sometimes|in:low,medium,high,critical',
            'type' => 'sometimes|string|max:100',
            'user_id' => 'sometimes|string|max:100',
            'ip_address' => 'sometimes|ip',
            'vault_id' => 'sometimes|uuid',
            'hours' => 'sometimes|integer|min:1|max:8760',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $query = SecurityAlert::query()->with(['vault']);

            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            if (!empty($validated['severity'])) {
                $query->where('severity', $validated['severity']);
            }

            if (!empty($validated['type'])) {
                $query->byType($validated['type']);
            }

            if (!empty($validated['user_id'])) {
                $query->byUser($validated['user_id']);
            }

            if (!empty($validated['ip_address'])) {
                $query->byIp($validated['ip_address']);
            }

            if (!empty($validated['vault_id'])) {
                $query->byVault($validated['vault_id']);
            }

            if (!empty($validated['hours'])) {
                $query->recent($validated['hours']);
            }

            $alerts = $query->orderByPriority()->paginate($validated['per_page'] ?? 20);

            return response()->json([
                'data' => $alerts->items(),
                'meta' => [
                    'current_page' => $alerts->currentPage(),
                    'per_page' => $alerts->perPage(),
                    'total' => $alerts->total(),
                    'last_page' => $alerts->lastPage(),
                    'filters_applied' => array_filter($validated),
                ],
                'message' => 'Security alerts retrieved successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve security alerts', [
                'error' => $e->getMessage(),
                'filters' => $validated,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve security alerts',
                    'code' => 'ALERTS_RETRIEVAL_ERROR',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get specific security alert
     */
    public function show(string $alertId): JsonResponse
    {
        try {
            $alert = SecurityAlert::with(['vault', 'triggeringAuditLog'])->findOrFail($alertId);

            return response()->json([
                'data' => [
                    'id' => $alert->id,
                    'type' => $alert->type,
                    'formatted_type' => $alert->formatted_type,
                    'severity' => $alert->severity,
                    'formatted_severity' => $alert->formatted_severity,
                    'status' => $alert->status,
                    'formatted_status' => $alert->formatted_status,
                    'message' => $alert->message,
                    'count' => $alert->count,
                    'first_occurrence' => $alert->first_occurrence,
                    'last_occurrence' => $alert->last_occurrence,
                    'user_id' => $alert->user_id,
                    'ip_address' => $alert->ip_address,
                    'vault_id' => $alert->vault_id,
                    'metadata' => $alert->metadata,
                    'acknowledged_by' => $alert->acknowledged_by,
                    'acknowledged_at' => $alert->acknowledged_at,
                    'resolved_by' => $alert->resolved_by,
                    'resolved_at' => $alert->resolved_at,
                    'resolution_notes' => $alert->resolution_notes,
                    'duration' => $alert->duration,
                    'created_at' => $alert->created_at,
                    'updated_at' => $alert->updated_at,
                    'vault' => $alert->vault,
                    'triggering_audit_log' => $alert->triggeringAuditLog,
                ],
                'message' => 'Security alert retrieved successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => [
                    'message' => 'Security alert not found',
                    'code' => 'ALERT_NOT_FOUND',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve security alert', [
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve security alert',
                    'code' => 'ALERT_RETRIEVAL_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Acknowledge security alert
     */
    public function acknowledge(Request $request, string $alertId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'sometimes|string|max:1000',
        ]);

        try {
            $alert = SecurityAlert::findOrFail($alertId);

            if ($alert->isResolved()) {
                return response()->json([
                    'error' => [
                        'message' => 'Cannot acknowledge a resolved alert',
                        'code' => 'ALERT_ALREADY_RESOLVED',
                    ],
                    'timestamp' => now()->toISOString(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $alert->acknowledge(Auth::id(), $validated['notes'] ?? null);

            Log::info('Security alert acknowledged', [
                'alert_id' => $alertId,
                'acknowledged_by' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'data' => [
                    'alert_id' => $alert->id,
                    'status' => $alert->status,
                    'acknowledged_by' => $alert->acknowledged_by,
                    'acknowledged_at' => $alert->acknowledged_at,
                ],
                'message' => 'Security alert acknowledged successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => [
                    'message' => 'Security alert not found',
                    'code' => 'ALERT_NOT_FOUND',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Failed to acknowledge security alert', [
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to acknowledge security alert',
                    'code' => 'ALERT_ACKNOWLEDGE_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Resolve security alert
     */
    public function resolve(Request $request, string $alertId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        try {
            $alert = SecurityAlert::findOrFail($alertId);

            if ($alert->isResolved()) {
                return response()->json([
                    'error' => [
                        'message' => 'Alert is already resolved',
                        'code' => 'ALERT_ALREADY_RESOLVED',
                    ],
                    'timestamp' => now()->toISOString(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $alert->resolve(Auth::id(), $validated['notes']);

            Log::info('Security alert resolved', [
                'alert_id' => $alertId,
                'resolved_by' => Auth::id(),
                'notes' => $validated['notes'],
            ]);

            return response()->json([
                'data' => [
                    'alert_id' => $alert->id,
                    'status' => $alert->status,
                    'resolved_by' => $alert->resolved_by,
                    'resolved_at' => $alert->resolved_at,
                    'resolution_notes' => $alert->resolution_notes,
                ],
                'message' => 'Security alert resolved successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => [
                    'message' => 'Security alert not found',
                    'code' => 'ALERT_NOT_FOUND',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Failed to resolve security alert', [
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to resolve security alert',
                    'code' => 'ALERT_RESOLVE_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark security alert as false positive
     */
    public function markFalsePositive(Request $request, string $alertId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        try {
            $alert = SecurityAlert::findOrFail($alertId);

            if ($alert->isResolved()) {
                return response()->json([
                    'error' => [
                        'message' => 'Alert is already resolved',
                        'code' => 'ALERT_ALREADY_RESOLVED',
                    ],
                    'timestamp' => now()->toISOString(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $alert->markFalsePositive(Auth::id(), $validated['notes']);

            Log::info('Security alert marked as false positive', [
                'alert_id' => $alertId,
                'resolved_by' => Auth::id(),
                'notes' => $validated['notes'],
            ]);

            return response()->json([
                'data' => [
                    'alert_id' => $alert->id,
                    'status' => $alert->status,
                    'resolved_by' => $alert->resolved_by,
                    'resolved_at' => $alert->resolved_at,
                    'resolution_notes' => $alert->resolution_notes,
                ],
                'message' => 'Security alert marked as false positive',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => [
                    'message' => 'Security alert not found',
                    'code' => 'ALERT_NOT_FOUND',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Failed to mark security alert as false positive', [
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to mark alert as false positive',
                    'code' => 'ALERT_FALSE_POSITIVE_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk acknowledge multiple alerts
     */
    public function bulkAcknowledge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array|min:1|max:100',
            'alert_ids.*' => 'uuid',
            'notes' => 'sometimes|string|max:1000',
        ]);

        try {
            $alerts = SecurityAlert::whereIn('id', $validated['alert_ids'])
                ->where('status', 'active')
                ->get();

            $acknowledgedCount = 0;
            $userId = Auth::id();
            $notes = $validated['notes'] ?? null;

            foreach ($alerts as $alert) {
                $alert->acknowledge($userId, $notes);
                $acknowledgedCount++;
            }

            Log::info('Bulk security alerts acknowledged', [
                'acknowledged_count' => $acknowledgedCount,
                'total_requested' => count($validated['alert_ids']),
                'acknowledged_by' => $userId,
            ]);

            return response()->json([
                'data' => [
                    'acknowledged_count' => $acknowledgedCount,
                    'total_requested' => count($validated['alert_ids']),
                    'acknowledged_by' => $userId,
                ],
                'message' => "Successfully acknowledged {$acknowledgedCount} security alerts",
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to bulk acknowledge security alerts', [
                'alert_ids' => $validated['alert_ids'],
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to bulk acknowledge security alerts',
                    'code' => 'BULK_ACKNOWLEDGE_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk resolve multiple alerts
     */
    public function bulkResolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array|min:1|max:100',
            'alert_ids.*' => 'uuid',
            'notes' => 'required|string|max:1000',
        ]);

        try {
            $alerts = SecurityAlert::whereIn('id', $validated['alert_ids'])
                ->whereIn('status', ['active', 'acknowledged'])
                ->get();

            $resolvedCount = 0;
            $userId = Auth::id();
            $notes = $validated['notes'];

            foreach ($alerts as $alert) {
                $alert->resolve($userId, $notes);
                $resolvedCount++;
            }

            Log::info('Bulk security alerts resolved', [
                'resolved_count' => $resolvedCount,
                'total_requested' => count($validated['alert_ids']),
                'resolved_by' => $userId,
            ]);

            return response()->json([
                'data' => [
                    'resolved_count' => $resolvedCount,
                    'total_requested' => count($validated['alert_ids']),
                    'resolved_by' => $userId,
                ],
                'message' => "Successfully resolved {$resolvedCount} security alerts",
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to bulk resolve security alerts', [
                'alert_ids' => $validated['alert_ids'],
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to bulk resolve security alerts',
                    'code' => 'BULK_RESOLVE_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get security alerts summary
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hours' => 'sometimes|integer|min:1|max:8760',
        ]);

        try {
            $hours = $validated['hours'] ?? 24;
            $stats = SecurityAlert::getRecentAlertsStats($hours);

            return response()->json([
                'data' => [
                    'summary' => $stats,
                    'active_alerts' => SecurityAlert::getActiveAlertsCount(),
                    'critical_alerts' => SecurityAlert::getCriticalAlertsCount(),
                    'period_hours' => $hours,
                ],
                'message' => 'Security alerts summary retrieved successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve security alerts summary', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve security alerts summary',
                    'code' => 'ALERTS_SUMMARY_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get security alerts trends
     */
    public function trends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        try {
            $days = $validated['days'] ?? 7;
            $startDate = now()->subDays($days);

            $dailyTrend = SecurityAlert::where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date')
                ->map->count;

            $severityTrend = SecurityAlert::where('created_at', '>=', $startDate)
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity');

            $typeTrend = SecurityAlert::where('created_at', '>=', $startDate)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->pluck('count', 'type');

            return response()->json([
                'data' => [
                    'period_days' => $days,
                    'daily_trend' => $dailyTrend,
                    'severity_distribution' => $severityTrend,
                    'type_distribution' => $typeTrend,
                    'total_alerts' => $dailyTrend->sum(),
                ],
                'message' => 'Security alerts trends retrieved successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve security alerts trends', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve security alerts trends',
                    'code' => 'ALERTS_TRENDS_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
