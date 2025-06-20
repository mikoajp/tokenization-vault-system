<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'severity',
        'status',
        'user_id',
        'ip_address',
        'vault_id',
        'message',
        'count',
        'first_occurrence',
        'last_occurrence',
        'metadata',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
        'triggering_audit_log_id',
        'auto_resolved',
        'auto_resolve_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'first_occurrence' => 'datetime',
        'last_occurrence' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'auto_resolved' => 'boolean',
        'auto_resolve_at' => 'datetime',
    ];

    // RELATIONSHIPS
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class, 'vault_id');
    }

    public function triggeringAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'triggering_audit_log_id');
    }

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['active', 'acknowledged']);
    }

    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeByVault($query, $vaultId)
    {
        return $query->where('vault_id', $vaultId);
    }

    public function scopeOrderByPriority($query)
    {
        return $query->orderByRaw("
            CASE severity
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END ASC
        ")->orderBy('created_at', 'desc');
    }

    public function scopeAutoResolvable($query)
    {
        return $query->where('auto_resolved', false)
            ->whereNotNull('auto_resolve_at')
            ->where('auto_resolve_at', '<=', now());
    }
    public function acknowledge(string $userId, string $notes = null): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function resolve(string $userId, string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function markFalsePositive(string $userId, string $notes = null): void
    {
        $this->update([
            'status' => 'false_positive',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function incrementCount(): void
    {
        $this->increment('count');
        $this->update(['last_occurrence' => now()]);
    }

    public function setAutoResolve(int $hours = 24): void
    {
        $this->update([
            'auto_resolve_at' => now()->addHours($hours),
        ]);
    }

    public function autoResolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'auto_resolved' => true,
            'resolved_at' => now(),
            'resolution_notes' => 'Automatically resolved due to timeout',
        ]);
    }

    // STATUS CHECKS
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'false_positive']);
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isHighSeverity(): bool
    {
        return in_array($this->severity, ['high', 'critical']);
    }

    public function requiresImmediateAttention(): bool
    {
        return $this->isCritical() && $this->isActive();
    }

    public function canAutoResolve(): bool
    {
        return !$this->auto_resolved &&
            $this->auto_resolve_at &&
            $this->auto_resolve_at->isPast();
    }

    // GETTERS
    public function getDurationAttribute(): ?string
    {
        if ($this->resolved_at) {
            return $this->created_at->diffForHumans($this->resolved_at, true);
        }

        return $this->created_at->diffForHumans(now(), true) . ' (ongoing)';
    }

    public function getSeverityBadgeClassAttribute(): string
    {
        return match($this->severity) {
            'critical' => 'badge-danger',
            'high' => 'badge-warning',
            'medium' => 'badge-info',
            'low' => 'badge-secondary',
            default => 'badge-light',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'active' => 'badge-danger',
            'acknowledged' => 'badge-warning',
            'resolved' => 'badge-success',
            'false_positive' => 'badge-secondary',
            default => 'badge-light',
        };
    }

    public function getFormattedTypeAttribute(): string
    {
        return match($this->type) {
            'multiple_failures' => 'Multiple Login Failures',
            'high_volume_detokenize' => 'High Volume Detokenization',
            'off_hours_access' => 'Off-Hours Access',
            'multiple_ip_access' => 'Multiple IP Access',
            'suspicious_pattern' => 'Suspicious Pattern',
            'rate_limit_exceeded' => 'Rate Limit Exceeded',
            'geo_anomaly' => 'Geographic Anomaly',
            default => ucwords(str_replace('_', ' ', $this->type)),
        };
    }

    public function getFormattedSeverityAttribute(): string
    {
        return ucfirst($this->severity);
    }

    public function getFormattedStatusAttribute(): string
    {
        return match($this->status) {
            'false_positive' => 'False Positive',
            default => ucfirst($this->status),
        };
    }
    public static function createFromAuditLog(AuditLog $auditLog, array $alertData): self
    {
        return self::create(array_merge($alertData, [
            'triggering_audit_log_id' => $auditLog->id,
            'user_id' => $auditLog->user_id,
            'ip_address' => $auditLog->ip_address,
            'vault_id' => $auditLog->vault_id,
            'first_occurrence' => $auditLog->created_at,
            'last_occurrence' => $auditLog->created_at,
        ]));
    }

    public static function findOrCreateSimilar(array $criteria, array $alertData): self
    {
        $existing = self::where($criteria)
            ->where('status', 'active')
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($existing) {
            $existing->incrementCount();
            return $existing;
        }

        return self::create($alertData);
    }

    public static function createMultipleFailuresAlert(string $ipAddress, int $count): self
    {
        return self::create([
            'type' => 'multiple_failures',
            'severity' => 'high',
            'ip_address' => $ipAddress,
            'message' => "Multiple failed operations from IP: {$ipAddress}",
            'count' => $count,
            'first_occurrence' => now()->subHour(),
            'last_occurrence' => now(),
            'metadata' => [
                'detection_threshold' => 5,
                'time_window_hours' => 1,
            ],
        ]);
    }

    public static function createHighVolumeAlert(string $userId, int $count, string $operation = 'detokenize'): self
    {
        return self::create([
            'type' => 'high_volume_detokenize',
            'severity' => 'medium',
            'user_id' => $userId,
            'message' => "High volume {$operation}: {$count} operations in 1 hour by user {$userId}",
            'count' => $count,
            'first_occurrence' => now()->startOfHour(),
            'last_occurrence' => now(),
            'metadata' => [
                'operation_type' => $operation,
                'detection_threshold' => 100,
                'time_window_hours' => 1,
            ],
        ]);
    }

    public static function createOffHoursAlert(string $userId, string $operation, int $hour): self
    {
        return self::create([
            'type' => 'off_hours_access',
            'severity' => 'medium',
            'user_id' => $userId,
            'message' => "Off-hours {$operation} operation at {$hour}:00 by user {$userId}",
            'count' => 1,
            'first_occurrence' => now(),
            'last_occurrence' => now(),
            'metadata' => [
                'operation_type' => $operation,
                'access_hour' => $hour,
                'business_hours' => '8-18',
            ],
        ]);
    }

    public static function getActiveAlertsCount(): int
    {
        return self::active()->count();
    }

    public static function getCriticalAlertsCount(): int
    {
        return self::critical()->active()->count();
    }

    public static function getRecentAlertsStats(int $hours = 24): array
    {
        $alerts = self::recent($hours)->get();

        return [
            'total' => $alerts->count(),
            'critical' => $alerts->where('severity', 'critical')->count(),
            'high' => $alerts->where('severity', 'high')->count(),
            'by_type' => $alerts->groupBy('type')->map->count(),
            'resolved' => $alerts->whereIn('status', ['resolved', 'false_positive'])->count(),
            'active' => $alerts->where('status', 'active')->count(),
        ];
    }
}
