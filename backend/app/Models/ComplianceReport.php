<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableTrait;

class ComplianceReport extends Model
{
    use HasFactory, HasUuids, AuditableTrait;

    protected $fillable = [
        'report_type', 'report_name', 'period_start', 'period_end',
        'status', 'file_path', 'file_hash', 'filters_applied',
        'summary_statistics', 'total_records', 'generation_time_seconds',
        'generated_by', 'access_granted_to', 'expires_at',

        'progress',              // 0-100% postęp
        'status_message',        // Komunikat o statusie
        'download_url',          // Link do pobrania
        'file_size',            // Rozmiar pliku w bajtach
        'email',                // Email do powiadomień
        'request_parameters',    // Dodatkowe parametry żądania
        'started_at',           // Kiedy rozpoczęto przetwarzanie
        'completed_at',         // Kiedy zakończono
        'error_message',        // Szczegółowy komunikat błędu
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'filters_applied' => 'array',
        'summary_statistics' => 'array',
        'access_granted_to' => 'array',
        'expires_at' => 'datetime',

        'request_parameters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function scopeByType($query, $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeAccessibleBy($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('generated_by', $userId)
                ->orWhereJsonContains('access_granted_to', $userId);
        });
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeProcessing($query)
    {
        return $query->whereIn('status', ['generating', 'processing']);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['queued', 'generating', 'processing']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByProgress($query, $minProgress)
    {
        return $query->where('progress', '>=', $minProgress);
    }

    public function markCompleted(string $filePath, string $fileHash, array $statistics): void
    {
        $this->update([
            'status' => 'completed',
            'progress' => 100,
            'file_path' => $filePath,
            'file_hash' => $fileHash,
            'summary_statistics' => $statistics,
            'generation_time_seconds' => now()->diffInSeconds($this->created_at),
            'completed_at' => now(),
            'status_message' => 'Report generation completed successfully',
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'status_message' => 'Report generation failed',
            'summary_statistics' => array_merge($this->summary_statistics ?? [], [
                'error' => $errorMessage,
                'failed_at' => now()->toISOString(),
            ]),
        ]);
    }

    public function markAsQueued(): void
    {
        $this->update([
            'status' => 'queued',
            'progress' => 0,
            'status_message' => 'Report queued for generation',
        ]);
    }

    public function markAsProcessing(string $message = 'Processing started'): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
            'status_message' => $message,
            'progress' => 5,
        ]);
    }

    public function updateProgress(int $progress, string $message = null): void
    {
        $updateData = [
            'progress' => min(100, max(0, $progress)), // Clamp between 0-100
        ];

        if ($message) {
            $updateData['status_message'] = $message;
        }

        $this->update($updateData);
    }

    public function setFileInfo(string $filePath, int $fileSize, string $downloadUrl = null): void
    {
        $updateData = [
            'file_path' => $filePath,
            'file_size' => $fileSize,
        ];

        if ($downloadUrl) {
            $updateData['download_url'] = $downloadUrl;
        }

        $this->update($updateData);
    }

    // HELPER METHODS
    public function hasAccess(string $userId): bool
    {
        return $this->generated_by === $userId ||
            in_array($userId, $this->access_granted_to ?? []);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['queued', 'generating', 'processing']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getDurationAttribute(): ?string
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffForHumans($this->completed_at, true);
        }

        if ($this->started_at && $this->isPending()) {
            return $this->started_at->diffForHumans(now(), true) . ' (in progress)';
        }

        return null;
    }

    public function getProgressPercentageAttribute(): string
    {
        return $this->progress . '%';
    }

    public function getFormattedFileSizeAttribute(): ?string
    {
        if (!$this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    // RELATIONSHIPS
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'vault_id', 'id');
    }

    public function generatedByUser()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
