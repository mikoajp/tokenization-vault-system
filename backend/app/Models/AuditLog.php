<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'vault_id', 'token_id', 'operation', 'result',
        'error_message', 'user_id', 'api_key_id', 'session_id',
        'ip_address', 'user_agent', 'request_id',
        'request_metadata', 'response_metadata', 'processing_time_ms',
        'risk_level', 'pci_relevant', 'compliance_reference'
    ];

    protected $casts = [
        'request_metadata' => 'array',
        'response_metadata' => 'array',
        'pci_relevant' => 'boolean',
    ];

    // Relationships
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class);
    }

    // Scopes
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

    // Methods
    public static function logOperation(array $data): self
    {
        return self::create($data);
    }
}
