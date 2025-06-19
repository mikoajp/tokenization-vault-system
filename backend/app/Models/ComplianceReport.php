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
        'generated_by', 'access_granted_to', 'expires_at'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'filters_applied' => 'array',
        'summary_statistics' => 'array',
        'access_granted_to' => 'array',
        'expires_at' => 'datetime',
    ];

    // Scopes
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

    // Methods
    public function markCompleted(string $filePath, string $fileHash, array $statistics): void
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_hash' => $fileHash,
            'summary_statistics' => $statistics,
            'generation_time_seconds' => now()->diffInSeconds($this->created_at)
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'summary_statistics' => ['error' => $errorMessage]
        ]);
    }

    public function hasAccess(string $userId): bool
    {
        return $this->generated_by === $userId ||
            in_array($userId, $this->access_granted_to ?? []);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }
}
