<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComplianceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ComplianceReportController extends Controller
{
    public function __construct(
        private ComplianceReportService $complianceReportService
    ) {}

    /**
     * Utwórz nowy raport compliance
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => 'required|in:pci_dss,sox,gdpr',
            'report_name' => 'sometimes|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'vault_id' => 'sometimes|uuid|exists:vaults,id',
            'email' => 'sometimes|email',
            'filters' => 'sometimes|array',
        ]);

        try {
            $report = $this->complianceReportService->createReport($validated);

            Log::info('Compliance report creation requested', [
                'report_id' => $report->id,
                'report_type' => $validated['report_type'],
                'requested_by' => Auth::id(),
            ]);

            return response()->json([
                'data' => [
                    'report_id' => $report->id,
                    'status' => $report->status,
                    'estimated_completion' => now()->addMinutes(15)->toISOString(),
                    'progress' => $report->progress,
                ],
                'message' => 'Compliance report generation started',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Failed to create compliance report', [
                'error' => $e->getMessage(),
                'params' => $validated,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to create compliance report',
                    'code' => 'REPORT_CREATION_FAILED',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Pobierz listę raportów użytkownika
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|in:queued,processing,completed,failed,archived',
            'report_type' => 'sometimes|in:pci_dss,sox,gdpr',
        ]);

        try {
            $perPage = $validated['per_page'] ?? 20;
            $reports = $this->complianceReportService->getUserReports(Auth::id(), $perPage);

            if (!empty($validated['status'])) {
                $reports->getCollection()->filter(function ($report) use ($validated) {
                    return $report->status === $validated['status'];
                });
            }

            if (!empty($validated['report_type'])) {
                $reports->getCollection()->filter(function ($report) use ($validated) {
                    return $report->report_type === $validated['report_type'];
                });
            }

            return response()->json([
                'data' => $reports->items(),
                'meta' => [
                    'current_page' => $reports->currentPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                    'last_page' => $reports->lastPage(),
                ],
                'message' => 'Compliance reports retrieved successfully',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve compliance reports', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve compliance reports',
                    'code' => 'REPORTS_RETRIEVAL_FAILED',
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Pobierz status konkretnego raportu
     */
    public function show(string $reportId): JsonResponse
    {
        try {
            $report = $this->complianceReportService->getReportStatus($reportId);

            if (!$report) {
                return response()->json([
                    'error' => [
                        'message' => 'Report not found',
                        'code' => 'REPORT_NOT_FOUND',
                    ],
                    'timestamp' => now()->toISOString(),
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$this->complianceReportService->userHasAccess($reportId, Auth::id())) {
                return response()->json([
                    'error' => [
                        'message' => 'Access denied to this report',
                        'code' => 'ACCESS_DENIED',
                    ],
                    'timestamp' => now()->toISOString(),
                ], Response::HTTP_FORBIDDEN);
            }

            return response()->json([
                'data' => [
                    'report_id' => $report->id,
                    'report_name' => $report->report_name,
                    'report_type' => $report->report_type,
                    'status' => $report->status,
                    'progress' => $report->progress,
                    'status_message' => $report->status_message,
                    'period_start' => $report->period_start,
                    'period_end' => $report->period_end,
                    'generated_by' => $report->generated_by,
                    'download_url' => $report->download_url,
                    'file_size' => $report->file_size,
                    'formatted_file_size' => $report->formatted_file_size,
                    'expires_at' => $report->expires_at,
                    'created_at' => $report->created_at,
                    'completed_at' => $report->completed_at,
                    'duration' => $report->duration,
                    'summary_statistics' => $report->summary_statistics,
                ],
                'message' => 'Report status retrieved successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve report status', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve report status',
                    'code' => 'REPORT_STATUS_FAILED',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Pobierz plik raportu
     */
    public function download(string $reportId): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            // Sprawdź dostęp
            if (!$this->complianceReportService->userHasAccess($reportId, Auth::id())) {
                abort(403, 'Access denied to this report');
            }

            $downloadInfo = $this->complianceReportService->downloadReport($reportId);

            Log::info('Compliance report downloaded', [
                'report_id' => $reportId,
                'user_id' => Auth::id(),
                'file_name' => $downloadInfo['file_name'],
            ]);

            return response()->download(
                storage_path('app/' . $downloadInfo['file_path']),
                $downloadInfo['file_name'],
                [
                    'Content-Type' => $downloadInfo['content_type'],
                    'Content-Length' => $downloadInfo['file_size'],
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to download compliance report', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            abort(500, 'Failed to download report: ' . $e->getMessage());
        }
    }

    /**
     * Usuń raport
     */
    public function destroy(string $reportId): JsonResponse
    {
        try {
            $deleted = $this->complianceReportService->deleteReport($reportId, Auth::id());

            if (!$deleted) {
                return response()->json([
                    'error' => [
                        'message' => 'Report not found or access denied',
                        'code' => 'REPORT_DELETE_FAILED',
                    ],
                    'timestamp' => now()->toISOString(),
                ], Response::HTTP_NOT_FOUND);
            }

            Log::info('Compliance report deleted', [
                'report_id' => $reportId,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Report deleted successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete compliance report', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to delete report',
                    'code' => 'REPORT_DELETE_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Udostępnij raport innym użytkownikom
     */
    public function share(Request $request, string $reportId): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'string|max:100',
        ]);

        try {
            $shared = $this->complianceReportService->shareReport(
                $reportId,
                Auth::id(),
                $validated['user_ids']
            );

            if (!$shared) {
                return response()->json([
                    'error' => [
                        'message' => 'Report not found or access denied',
                        'code' => 'REPORT_SHARE_FAILED',
                    ],
                    'timestamp' => now()->toISOString(),
                ], Response::HTTP_NOT_FOUND);
            }

            Log::info('Compliance report shared', [
                'report_id' => $reportId,
                'owner_id' => Auth::id(),
                'shared_with' => $validated['user_ids'],
            ]);

            return response()->json([
                'message' => 'Report shared successfully',
                'data' => [
                    'shared_with_count' => count($validated['user_ids']),
                    'shared_with' => $validated['user_ids'],
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to share compliance report', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to share report',
                    'code' => 'REPORT_SHARE_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Przedłuż ważność raportu
     */
    public function extend(Request $request, string $reportId): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        try {
            $days = $validated['days'] ?? 30;
            $extended = $this->complianceReportService->extendReportExpiry($reportId, Auth::id(), $days);

            if (!$extended) {
                return response()->json([
                    'error' => [
                        'message' => 'Report not found or access denied',
                        'code' => 'REPORT_EXTEND_FAILED',
                    ],
                    'timestamp' => now()->toISOString(),
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'message' => 'Report expiry extended successfully',
                'data' => [
                    'extended_by_days' => $days,
                    'new_expiry_date' => now()->addDays($days)->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to extend compliance report', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to extend report expiry',
                    'code' => 'REPORT_EXTEND_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Pobierz statystyki raportów
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        try {
            $days = $validated['days'] ?? 30;
            $stats = $this->complianceReportService->getReportStats($days);

            return response()->json([
                'data' => $stats,
                'meta' => [
                    'period_days' => $days,
                    'generated_at' => now()->toISOString(),
                ],
                'message' => 'Report statistics retrieved successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve report statistics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve report statistics',
                    'code' => 'REPORT_STATS_ERROR',
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
