<?php

namespace App\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\ComplianceReport;
use App\Jobs\GenerateComplianceReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ComplianceReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ComplianceReport::query()
            ->accessibleBy(Auth::id());

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        $reports = $query->latest()->paginate(25);

        return response()->json([
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => 'required|in:audit_summary,token_usage,vault_activity,compliance_overview',
            'report_name' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'filters_applied' => 'array',
            'access_granted_to' => 'array',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $report = ComplianceReport::create([
            ...$validated,
            'status' => 'pending',
            'generated_by' => Auth::id(),
        ]);

        // Dispatch job to generate report
        GenerateComplianceReport::dispatch($report);

        return response()->json([
            'data' => $report,
            'message' => 'Report generation started'
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $report = ComplianceReport::findOrFail($id);

        if (!$report->isAccessibleBy(Auth::id())) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        return response()->json(['data' => $report]);
    }

    public function download(string $id)
    {
        $report = ComplianceReport::findOrFail($id);

        if (!$report->isAccessibleBy(Auth::id())) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (!$report->isCompleted() || !$report->file_path) {
            return response()->json(['error' => 'Report not ready for download'], 400);
        }

        if ($report->isExpired()) {
            return response()->json(['error' => 'Report has expired'], 410);
        }

        if (!$report->verifyFileIntegrity()) {
            return response()->json(['error' => 'Report file integrity check failed'], 500);
        }

        return Storage::download($report->file_path, $report->report_name . '.pdf');
    }

    public function destroy(string $id): JsonResponse
    {
        $report = ComplianceReport::findOrFail($id);

        if (!$report->isAccessibleBy(Auth::id())) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $report->cleanup();
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }

    public function grantAccess(string $id, Request $request): JsonResponse
    {
        $report = ComplianceReport::findOrFail($id);

        if ($report->generated_by !== Auth::id()) {
            return response()->json(['error' => 'Only report owner can grant access'], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $report->grantAccessTo($validated['user_ids']);

        return response()->json(['message' => 'Access granted successfully']);
    }

    public function revokeAccess(string $id, Request $request): JsonResponse
    {
        $report = ComplianceReport::findOrFail($id);

        if ($report->generated_by !== Auth::id()) {
            return response()->json(['error' => 'Only report owner can revoke access'], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $report->revokeAccessFrom($validated['user_ids']);

        return response()->json(['message' => 'Access revoked successfully']);
    }
}