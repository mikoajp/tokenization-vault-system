<?php

namespace App\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\SecurityAlert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SecurityAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SecurityAlert::query();

        // Apply filters
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('severity')) {
            $query->bySeverity($request->severity);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('vault_id')) {
            $query->forVault($request->vault_id);
        }

        if ($request->has('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->has('ip_address')) {
            $query->fromIp($request->ip_address);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->inDateRange($request->date_from, $request->date_to);
        }

        // Default to unresolved alerts if no status filter
        if (!$request->has('status')) {
            $query->unresolved();
        }

        $perPage = min($request->get('per_page', 25), 100);
        $alerts = $query->latest('last_occurrence')->paginate($perPage);

        return response()->json([
            'data' => $alerts->items(),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ]
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $alert = SecurityAlert::with([
            'user',
            'acknowledgedByUser',
            'resolvedByUser',
            'triggeringAuditLog'
        ])->findOrFail($id);

        return response()->json(['data' => $alert]);
    }

    public function acknowledge(string $id, Request $request): JsonResponse
    {
        $alert = SecurityAlert::findOrFail($id);

        if ($alert->isResolved()) {
            return response()->json(['error' => 'Cannot acknowledge resolved alert'], 400);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        $alert->acknowledge(Auth::id(), $validated['notes'] ?? null);

        return response()->json([
            'data' => $alert->fresh(),
            'message' => 'Alert acknowledged successfully'
        ]);
    }

    public function resolve(string $id, Request $request): JsonResponse
    {
        $alert = SecurityAlert::findOrFail($id);

        if ($alert->isResolved()) {
            return response()->json(['error' => 'Alert is already resolved'], 400);
        }

        $validated = $request->validate([
            'notes' => 'required|string|max:1000'
        ]);

        $alert->resolve(Auth::id(), $validated['notes']);

        return response()->json([
            'data' => $alert->fresh(),
            'message' => 'Alert resolved successfully'
        ]);
    }

    public function bulkAcknowledge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'exists:security_alerts,id',
            'notes' => 'nullable|string|max:1000'
        ]);

        $alerts = SecurityAlert::whereIn('id', $validated['alert_ids'])
            ->unresolved()
            ->get();

        $acknowledged = 0;
        foreach ($alerts as $alert) {
            if (!$alert->isAcknowledged()) {
                $alert->acknowledge(Auth::id(), $validated['notes'] ?? null);
                $acknowledged++;
            }
        }

        return response()->json([
            'message' => "Successfully acknowledged {$acknowledged} alerts"
        ]);
    }

    public function bulkResolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'exists:security_alerts,id',
            'notes' => 'required|string|max:1000'
        ]);

        $alerts = SecurityAlert::whereIn('id', $validated['alert_ids'])
            ->unresolved()
            ->get();

        $resolved = 0;
        foreach ($alerts as $alert) {
            $alert->resolve(Auth::id(), $validated['notes']);
            $resolved++;
        }

        return response()->json([
            'message' => "Successfully resolved {$resolved} alerts"
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = SecurityAlert::query();

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->inDateRange($request->date_from, $request->date_to);
        }

        $stats = [
            'total_alerts' => $query->count(),
            'open_alerts' => $query->clone()->open()->count(),
            'acknowledged_alerts' => $query->clone()->acknowledged()->count(),
            'resolved_alerts' => $query->clone()->resolved()->count(),
            'critical_alerts' => $query->clone()->critical()->count(),
            'high_severity_alerts' => $query->clone()->highSeverity()->count(),
            'alerts_by_type' => $query->clone()
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'alerts_by_severity' => $query->clone()
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity'),
            'alerts_by_status' => $query->clone()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'top_affected_vaults' => $query->clone()
                ->whereNotNull('vault_id')
                ->selectRaw('vault_id, COUNT(*) as count')
                ->groupBy('vault_id')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'vault_id'),
            'top_source_ips' => $query->clone()
                ->whereNotNull('ip_address')
                ->selectRaw('ip_address, COUNT(*) as count')
                ->groupBy('ip_address')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'ip_address'),
        ];

        return response()->json(['data' => $stats]);
    }

    public function autoResolve(): JsonResponse
    {
        $alerts = SecurityAlert::dueForAutoResolve()->get();
        
        $resolved = 0;
        foreach ($alerts as $alert) {
            $alert->autoResolve();
            $resolved++;
        }

        return response()->json([
            'message' => "Auto-resolved {$resolved} alerts"
        ]);
    }
}