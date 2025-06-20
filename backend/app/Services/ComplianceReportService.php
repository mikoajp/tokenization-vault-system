<?php

namespace App\Services;

use App\Jobs\GenerateComplianceReport;
use App\Models\ComplianceReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplianceReportService
{
    /**
     * Utwórz nowy raport compliance
     */
    public function createReport(array $params): ComplianceReport
    {
        $reportId = Str::uuid();

        $report = ComplianceReport::create([
            'report_type' => $params['report_type'] ?? 'pci_dss',
            'report_name' => $params['report_name'] ?? $this->generateReportName($params),
            'period_start' => Carbon::parse($params['start_date']),
            'period_end' => Carbon::parse($params['end_date']),
            'status' => 'queued',
            'generated_by' => Auth::id() ?? $params['requested_by'],
            'email' => $params['email'] ?? null,
            'filters_applied' => $params['filters'] ?? [],
            'request_parameters' => $params,
            'progress' => 0,
            'status_message' => 'Report queued for generation',
            'expires_at' => now()->addDays(30),
        ]);

        Queue::pushOn('compliance_reports', new GenerateComplianceReport([
            'report_id' => $report->id,
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date'],
            'report_type' => $params['report_type'] ?? 'pci_dss',
            'vault_id' => $params['vault_id'] ?? null,
            'requested_by' => Auth::id() ?? $params['requested_by'],
            'email' => $params['email'] ?? null,
        ]));

        return $report;
    }

    /**
     * Pobierz status raportu
     */
    public function getReportStatus(string $reportId): ?ComplianceReport
    {
        return ComplianceReport::where('id', $reportId)
            ->orWhere('report_id', $reportId)
            ->first();
    }

    /**
     * Pobierz listę raportów dla użytkownika
     */
    public function getUserReports(string $userId, int $limit = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return ComplianceReport::accessibleBy($userId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Pobierz plik raportu
     */
    public function downloadReport(string $reportId): array
    {
        $report = $this->getReportStatus($reportId);

        if (!$report) {
            throw new \Exception('Report not found');
        }

        if (!$report->isCompleted()) {
            throw new \Exception('Report is not ready for download');
        }

        if ($report->isExpired()) {
            throw new \Exception('Report has expired');
        }

        if (!Storage::exists($report->file_path)) {
            throw new \Exception('Report file not found');
        }

        return [
            'file_path' => $report->file_path,
            'file_name' => basename($report->file_path),
            'content_type' => $this->getContentType($report->report_type),
            'file_size' => $report->file_size,
        ];
    }

    /**
     * Usuń raport
     */
    public function deleteReport(string $reportId, string $userId): bool
    {
        $report = ComplianceReport::where('id', $reportId)
            ->where('generated_by', $userId)
            ->first();

        if (!$report) {
            return false;
        }

        if ($report->file_path && Storage::exists($report->file_path)) {
            Storage::delete($report->file_path);
        }

        return $report->delete();
    }

    /**
     * Archiwizuj stare raporty
     */
    public function archiveExpiredReports(): int
    {
        $expiredReports = ComplianceReport::where('expires_at', '<', now())
            ->where('status', 'completed')
            ->get();

        $archivedCount = 0;

        foreach ($expiredReports as $report) {
            try {
                if ($report->file_path && Storage::exists($report->file_path)) {
                    Storage::delete($report->file_path);
                }

                $report->update([
                    'status' => 'archived',
                    'file_path' => null,
                    'download_url' => null,
                ]);

                $archivedCount++;
            } catch (\Exception $e) {
                \Log::error('Failed to archive report', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $archivedCount;
    }

    /**
     * Pobierz statystyki raportów
     */
    public function getReportStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'total_reports' => ComplianceReport::where('created_at', '>=', $since)->count(),
            'completed_reports' => ComplianceReport::completed()->where('created_at', '>=', $since)->count(),
            'failed_reports' => ComplianceReport::failed()->where('created_at', '>=', $since)->count(),
            'pending_reports' => ComplianceReport::pending()->count(),
            'reports_by_type' => ComplianceReport::where('created_at', '>=', $since)
                ->selectRaw('report_type, COUNT(*) as count')
                ->groupBy('report_type')
                ->pluck('count', 'report_type'),
            'average_generation_time' => ComplianceReport::completed()
                ->where('created_at', '>=', $since)
                ->whereNotNull('generation_time_seconds')
                ->avg('generation_time_seconds'),
        ];
    }

    /**
     * Wygeneruj nazwę raportu
     */
    private function generateReportName(array $params): string
    {
        $type = strtoupper($params['report_type'] ?? 'PCI_DSS');
        $startDate = Carbon::parse($params['start_date'])->format('Y-m-d');
        $endDate = Carbon::parse($params['end_date'])->format('Y-m-d');

        return "{$type} Compliance Report ({$startDate} to {$endDate})";
    }

    /**
     * Pobierz typ contentu dla raportu
     */
    private function getContentType(string $reportType): string
    {
        return match($reportType) {
            'pci_dss', 'sox', 'gdpr' => 'application/pdf',
            'csv_export' => 'text/csv',
            'json_export' => 'application/json',
            default => 'application/octet-stream',
        };
    }

    /**
     * Sprawdź czy użytkownik ma dostęp do raportu
     */
    public function userHasAccess(string $reportId, string $userId): bool
    {
        $report = $this->getReportStatus($reportId);

        if (!$report) {
            return false;
        }

        return $report->hasAccess($userId);
    }

    /**
     * Udostępnij raport innemu użytkownikowi
     */
    public function shareReport(string $reportId, string $ownerUserId, array $userIds): bool
    {
        $report = ComplianceReport::where('id', $reportId)
            ->where('generated_by', $ownerUserId)
            ->first();

        if (!$report) {
            return false;
        }

        $currentAccess = $report->access_granted_to ?? [];
        $newAccess = array_unique(array_merge($currentAccess, $userIds));

        return $report->update([
            'access_granted_to' => $newAccess
        ]);
    }

    /**
     * Przedłuż ważność raportu
     */
    public function extendReportExpiry(string $reportId, string $userId, int $days = 30): bool
    {
        $report = ComplianceReport::where('id', $reportId)
            ->where(function($query) use ($userId) {
                $query->where('generated_by', $userId)
                    ->orWhereJsonContains('access_granted_to', $userId);
            })
            ->first();

        if (!$report) {
            return false;
        }

        $newExpiryDate = $report->expires_at->addDays($days);

        return $report->update([
            'expires_at' => $newExpiryDate
        ]);
    }
}
