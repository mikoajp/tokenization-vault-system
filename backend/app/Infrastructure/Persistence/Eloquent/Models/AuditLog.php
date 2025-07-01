<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

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
        'triggered_alerts' => 'array',
        'archived_at' => 'datetime',
        'compressed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // Relationships
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function securityAlerts(): HasMany
    {
        return $this->hasMany(SecurityAlert::class, 'triggering_audit_log_id');
    }

    // Scopes
    public function scopeForVault($query, $vaultId)
    {
        return $query->where('vault_id', $vaultId);
    }

    public function scopeByOperation($query, $operation)
    {
        return $query->where('operation', $operation);
    }

    public function scopeByResult($query, $result)
    {
        return $query->where('result', $result);
    }

    public function scopeHighRisk($query)
    {
        return $query->where('risk_level', 'high');
    }

    public function scopePciRelevant($query)
    {
        return $query->where('pci_relevant', true);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    // Methods
    public function markAsProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    public function archive(string $location): void
    {
        $this->update([
            'archived_at' => now(),
            'archive_location' => $location
        ]);
    }

    public function compress(): void
    {
        $this->update(['compressed_at' => now()]);
    }

    public function addTriggeredAlert(string $alertId): void
    {
        $alerts = $this->triggered_alerts ?? [];
        $alerts[] = $alertId;
        $this->update(['triggered_alerts' => array_unique($alerts)]);
    }

    public function isHighRisk(): bool
    {
        return $this->risk_level === 'high';
    }

    public function isPciRelevant(): bool
    {
        return $this->pci_relevant === true;
    }

    public function isArchived(): bool
    {
        return !is_null($this->archived_at);
    }

    public function isProcessed(): bool
    {
        return !is_null($this->processed_at);
    }

    public function getProcessingTimeInSeconds(): float
    {
        return $this->processing_time_ms / 1000;
    }

    public function shouldTriggerAlert(): bool
    {
        return $this->result === 'error' || 
               $this->risk_level === 'high' ||
               $this->operation === 'unauthorized_access';
    }

    public function generateComplianceReference(): string
    {
        return sprintf(
            'AUDIT-%s-%s',
            $this->created_at->format('Ymd'),
            strtoupper(substr($this->id, 0, 8))
        );
    }

    public function toComplianceArray(): array
    {
        return [
            'audit_id' => $this->id,
            'timestamp' => $this->created_at->toISOString(),
            'vault_id' => $this->vault_id,
            'operation' => $this->operation,
            'result' => $this->result,
            'user_context' => [
                'user_id' => $this->user_id,
                'api_key_id' => $this->api_key_id,
                'ip_address' => $this->ip_address,
                'session_id' => $this->session_id,
            ],
            'compliance_reference' => $this->compliance_reference,
            'pci_relevant' => $this->pci_relevant,
            'risk_level' => $this->risk_level,
            'processing_time_ms' => $this->processing_time_ms,
        ];
    }
}