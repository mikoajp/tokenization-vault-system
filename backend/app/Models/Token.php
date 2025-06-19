<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditableTrait;

class Token extends Model
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'vault_id', 'token_value', 'format_preserved_token',
        'token_type', 'metadata', 'expires_at', 'key_version', 'status'
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_data', 'data_hash', 'checksum'
    ];

    // Relationships
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeByVault($query, $vaultId)
    {
        return $query->where('vault_id', $vaultId);
    }

    public function scopeByTokenValue($query, $tokenValue)
    {
        return $query->where('token_value', $tokenValue);
    }

    // Methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function revoke(): void
    {
        $this->update(['status' => 'revoked']);
    }

    public function expire(): void
    {
        $this->update([
            'status' => 'expired',
            'expires_at' => now()
        ]);
    }

    public function markCompromised(): void
    {
        $this->update(['status' => 'compromised']);
    }
}
