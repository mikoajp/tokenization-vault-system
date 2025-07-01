<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

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
        'first_occurrence' => 'datetime',
        'last_occurrence' => 'datetime',
        'metadata' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'auto_resolved' => 'boolean',
        'auto_resolve_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function triggeringAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'triggering_audit_log_id');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeForVault($query, $vaultId)
    {
        return $query->where('vault_id', $vaultId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFromIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('first_occurrence', [$startDate, $endDate]);
    }

    public function scopeUnacknowledged($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['open', 'acknowledged']);
    }

    public function scopeDueForAutoResolve($query)
    {
        return $query->where('auto_resolved', true)
            ->where('auto_resolve_at', '<=', now())
            ->whereIn('status', ['open', 'acknowledged']);
    }

    // Methods
    public function acknowledge(string $userId, string $notes = null): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
            'resolution_notes' => $notes
        ]);
    }

    public function resolve(string $userId, string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes
        ]);
    }

    public function autoResolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => 'Auto-resolved after timeout period'
        ]);
    }

    public function incrementCount(): void
    {
        $this->update([
            'count' => $this->count + 1,
            'last_occurrence' => now()
        ]);
    }

    public function updateMetadata(array $newMetadata): void
    {
        $metadata = array_merge($this->metadata ?? [], $newMetadata);
        $this->update(['metadata' => $metadata]);
    }

    public function scheduleAutoResolve(int $hoursFromNow = 24): void
    {
        $this->update([
            'auto_resolved' => true,
            'auto_resolve_at' => now()->addHours($hoursFromNow)
        ]);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isHighSeverity(): bool
    {
        return in_array($this->severity, ['high', 'critical']);
    }

    public function isDueForAutoResolve(): bool
    {
        return $this->auto_resolved &&
               $this->auto_resolve_at &&
               $this->auto_resolve_at <= now() &&
               !$this->isResolved();
    }

    public function getAgeInHours(): int
    {
        return $this->first_occurrence->diffInHours(now());
    }

    public function getTimeSinceLastOccurrence(): int
    {
        return $this->last_occurrence->diffInMinutes(now());
    }

    public function getSeverityColor(): string
    {
        return match($this->severity) {
            'critical' => '#dc2626',
            'high' => '#ea580c',
            'medium' => '#d97706',
            'low' => '#65a30d',
            default => '#6b7280'
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'open' => '#dc2626',
            'acknowledged' => '#d97706',
            'resolved' => '#16a34a',
            default => '#6b7280'
        };
    }

    public function toNotificationArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,
            'vault_id' => $this->vault_id,
            'user_id' => $this->user_id,
            'ip_address' => $this->ip_address,
            'count' => $this->count,
            'first_occurrence' => $this->first_occurrence->toISOString(),
            'last_occurrence' => $this->last_occurrence->toISOString(),
            'metadata' => $this->metadata,
        ];
    }
}