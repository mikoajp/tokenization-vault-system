<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Shared\Traits\AuditableTrait;

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
        'queue_job_id',          // ID zadania w kolejce
        'retry_count',           // Liczba ponownych prób
        'error_details',         // Szczegóły błędów
        'estimated_completion',  // Szacowany czas zakończenia
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'filters_applied' => 'array',
        'summary_statistics' => 'array',
        'access_granted_to' => 'array',
        'expires_at' => 'datetime',
        'error_details' => 'array',
        'estimated_completion' => 'datetime',
    ];

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeAccessibleBy($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('generated_by', $userId)
                ->orWhereJsonContains('access_granted_to', $userId);
        });
    }

    // Methods
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'status_message' => 'Report generation in progress'
        ]);
    }

    public function markAsCompleted(string $filePath, string $fileHash): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_hash' => $fileHash,
            'progress' => 100,
            'status_message' => 'Report generated successfully'
        ]);
    }

    public function markAsFailed(string $errorMessage, array $errorDetails = []): void
    {
        $this->update([
            'status' => 'failed',
            'status_message' => $errorMessage,
            'error_details' => $errorDetails,
            'retry_count' => ($this->retry_count ?? 0) + 1
        ]);
    }

    public function updateProgress(int $progress, string $message = null): void
    {
        $updates = ['progress' => min(100, max(0, $progress))];
        
        if ($message) {
            $updates['status_message'] = $message;
        }

        $this->update($updates);
    }

    public function grantAccessTo(array $userIds): void
    {
        $currentAccess = $this->access_granted_to ?? [];
        $newAccess = array_unique(array_merge($currentAccess, $userIds));
        
        $this->update(['access_granted_to' => $newAccess]);
    }

    public function revokeAccessFrom(array $userIds): void
    {
        $currentAccess = $this->access_granted_to ?? [];
        $newAccess = array_diff($currentAccess, $userIds);
        
        $this->update(['access_granted_to' => array_values($newAccess)]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isAccessibleBy(string $userId): bool
    {
        return $this->generated_by === $userId ||
               in_array($userId, $this->access_granted_to ?? []);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && ($this->retry_count ?? 0) < 3;
    }

    public function getProgressPercentage(): int
    {
        return $this->progress ?? 0;
    }

    public function getEstimatedTimeRemaining(): ?int
    {
        if (!$this->estimated_completion || $this->isCompleted()) {
            return null;
        }

        $remaining = $this->estimated_completion->diffInSeconds(now());
        return max(0, $remaining);
    }

    public function generateDownloadUrl(): ?string
    {
        if (!$this->isCompleted() || !$this->file_path) {
            return null;
        }

        return route('compliance.reports.download', $this->id);
    }

    public function getFileSize(): ?int
    {
        if (!$this->file_path || !file_exists($this->file_path)) {
            return null;
        }

        return filesize($this->file_path);
    }

    public function verifyFileIntegrity(): bool
    {
        if (!$this->file_path || !$this->file_hash || !file_exists($this->file_path)) {
            return false;
        }

        return hash_file('sha256', $this->file_path) === $this->file_hash;
    }

    public function cleanup(): void
    {
        if ($this->file_path && file_exists($this->file_path)) {
            unlink($this->file_path);
        }

        $this->update([
            'file_path' => null,
            'file_hash' => null,
            'status' => 'archived'
        ]);
    }
}