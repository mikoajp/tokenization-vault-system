<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'vault_id', 'token_id', 'operation', 'result',
        'error_message', 'user_id', 'api_key_id', 'session_id',
        'ip_address', 'user_agent', 'request_id',
        'request_metadata', 'response_metadata', 'processing_time_ms',
        'risk_level', 'pci_relevant', 'compliance_reference',

        'archived_at',           // Kiedy zarchiwizowano
        'archive_location',      // Lokalizacja archiwum
        'compressed_at',         // Kiedy skompresowano
        'processed_at',          // Kiedy przetworzono przez queue
        'triggered_alerts',      // Czy wywołano alerty
    ];

    protected $casts = [
        'request_metadata' => 'array',
        'response_metadata' => 'array',
        'pci_relevant' => 'boolean',

        'triggered_alerts' => 'boolean',
        'archived_at' => 'datetime',
        'compressed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class);
    }

    public function securityAlerts(): HasMany
    {
        return $this->hasMany(SecurityAlert::class, 'triggering_audit_log_id');
    }
    public function scopeByOperation($query, $operation)
    {
        return $query->where('operation', $operation);
    }

    public function scopeByResult($query, $result)
    {
        return $query->where('result', $result);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByVault($query, $vaultId)
    {
        return $query->where('vault_id', $vaultId);
    }

    public function scopePciRelevant($query)
    {
        return $query->where('pci_relevant', true);
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeByIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeFailures($query)
    {
        return $query->where('result', 'failure');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('result', 'success');
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeProcessed($query)
    {
        return $query->whereNotNull('processed_at');
    }

    public function scopePendingProcessing($query)
    {
        return $query->whereNull('processed_at');
    }

    public function scopeTriggeredAlerts($query)
    {
        return $query->where('triggered_alerts', true);
    }

    public function scopeByRiskLevel($query, $riskLevel)
    {
        return $query->where('risk_level', $riskLevel);
    }

    public function scopeCritical($query)
    {
        return $query->where('risk_level', 'critical');
    }

    public function scopeDetokenizeOperations($query)
    {
        return $query->where('operation', 'detokenize');
    }

    public function scopeTokenizeOperations($query)
    {
        return $query->where('operation', 'tokenize');
    }

    public function scopeOffHours($query)
    {
        return $query->whereRaw('HOUR(created_at) NOT BETWEEN 8 AND 18');
    }
    public static function logOperation(array $data): self
    {
        return self::create($data);
    }

    public static function logAsync(array $data): self
    {
        // Dodaj timestamp przetwarzania
        $data['processed_at'] = now();

        return self::create($data);
    }

    public function markAsArchived(string $archiveLocation): void
    {
        $this->update([
            'archived_at' => now(),
            'archive_location' => $archiveLocation,
        ]);
    }

    public function markAsCompressed(): void
    {
        $this->update([
            'compressed_at' => now(),
        ]);
    }

    public function markAlertsTriggered(): void
    {
        $this->update([
            'triggered_alerts' => true,
        ]);
    }
    public function isArchived(): bool
    {
        return !is_null($this->archived_at);
    }

    public function isProcessed(): bool
    {
        return !is_null($this->processed_at);
    }

    public function hasTriggeredAlerts(): bool
    {
        return $this->triggered_alerts === true;
    }

    public function isHighRisk(): bool
    {
        return in_array($this->risk_level, ['high', 'critical']);
    }

    public function isCritical(): bool
    {
        return $this->risk_level === 'critical';
    }

    public function isFailure(): bool
    {
        return $this->result === 'failure';
    }

    public function isPciRelevant(): bool
    {
        return $this->pci_relevant === true;
    }

    public function getFormattedOperationAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->operation));
    }

    public function getProcessingTimeAttribute(): ?string
    {
        if ($this->processing_time_ms) {
            if ($this->processing_time_ms < 1000) {
                return $this->processing_time_ms . 'ms';
            }
            return round($this->processing_time_ms / 1000, 2) . 's';
        }

        return null;
    }

    public function getRiskBadgeClassAttribute(): string
    {
        return match($this->risk_level) {
            'critical' => 'badge-danger',
            'high' => 'badge-warning',
            'medium' => 'badge-info',
            'low' => 'badge-secondary',
            default => 'badge-light',
        };
    }

    public function getResultBadgeClassAttribute(): string
    {
        return match($this->result) {
            'success' => 'badge-success',
            'failure' => 'badge-danger',
            'partial' => 'badge-warning',
            default => 'badge-secondary',
        };
    }

    public static function getOperationStats(string $operation, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        return [
            'total' => self::byOperation($operation)->inDateRange($startDate, now())->count(),
            'successful' => self::byOperation($operation)->byResult('success')->inDateRange($startDate, now())->count(),
            'failed' => self::byOperation($operation)->byResult('failure')->inDateRange($startDate, now())->count(),
            'high_risk' => self::byOperation($operation)->highRisk()->inDateRange($startDate, now())->count(),
        ];
    }

    public static function getUserActivityStats(string $userId, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        return [
            'total_operations' => self::byUser($userId)->inDateRange($startDate, now())->count(),
            'operations_by_type' => self::byUser($userId)
                ->inDateRange($startDate, now())
                ->selectRaw('operation, COUNT(*) as count')
                ->groupBy('operation')
                ->pluck('count', 'operation')
                ->toArray(),
            'failure_rate' => self::calculateFailureRate($userId, $days),
            'risk_distribution' => self::byUser($userId)
                ->inDateRange($startDate, now())
                ->selectRaw('risk_level, COUNT(*) as count')
                ->groupBy('risk_level')
                ->pluck('count', 'risk_level')
                ->toArray(),
        ];
    }

    private static function calculateFailureRate(string $userId, int $days): float
    {
        $total = self::byUser($userId)->inDateRange(now()->subDays($days), now())->count();

        if ($total === 0) {
            return 0.0;
        }

        $failures = self::byUser($userId)->failures()->inDateRange(now()->subDays($days), now())->count();

        return round(($failures / $total) * 100, 2);
    }

    public static function getRecentActivity(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return self::with(['vault'])
            ->recent()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getSecurityEvents(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return self::recent($hours)
            ->where(function($query) {
                $query->failures()
                    ->orWhere('risk_level', 'critical')
                    ->orWhere('triggered_alerts', true);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
