<?php

namespace App\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Infrastructure\Persistence\Eloquent\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        // Apply filters
        if ($request->has('vault_id')) {
            $query->forVault($request->vault_id);
        }

        if ($request->has('operation')) {
            $query->byOperation($request->operation);
        }

        if ($request->has('result')) {
            $query->byResult($request->result);
        }

        if ($request->has('risk_level')) {
            if ($request->risk_level === 'high') {
                $query->highRisk();
            }
        }

        if ($request->has('pci_relevant')) {
            $query->pciRelevant();
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->inDateRange($request->date_from, $request->date_to);
        }

        // Pagination
        $perPage = min($request->get('per_page', 25), 100);
        $auditLogs = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => AuditLogResource::collection($auditLogs->items()),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
            ]
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $auditLog = AuditLog::findOrFail($id);
        
        return response()->json([
            'data' => new AuditLogResource($auditLog)
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        if ($request->has('vault_id')) {
            $query->forVault($request->vault_id);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->inDateRange($request->date_from, $request->date_to);
        }

        $stats = [
            'total_operations' => $query->count(),
            'successful_operations' => $query->clone()->byResult('success')->count(),
            'failed_operations' => $query->clone()->byResult('error')->count(),
            'high_risk_operations' => $query->clone()->highRisk()->count(),
            'pci_relevant_operations' => $query->clone()->pciRelevant()->count(),
            'operations_by_type' => $query->clone()
                ->selectRaw('operation, COUNT(*) as count')
                ->groupBy('operation')
                ->pluck('count', 'operation'),
            'operations_by_result' => $query->clone()
                ->selectRaw('result, COUNT(*) as count')
                ->groupBy('result')
                ->pluck('count', 'result'),
        ];

        return response()->json(['data' => $stats]);
    }
}