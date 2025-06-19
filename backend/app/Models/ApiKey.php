<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditableTrait;

class ApiKey extends Model
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'name', 'key_prefix', 'vault_permissions', 'operation_permissions',
        'ip_whitelist', 'rate_limit_per_hour', 'status', 'expires_at',
        'owner_type', 'owner_id', 'description'
    ];

    protected $casts = [
        'vault_permissions' => 'array',
        'operation_permissions' => 'array',
        'ip_whitelist' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'key_hash'
    ];

    // Relationships
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

    // Methods
    public function hasVaultPermission(string $vaultId): bool
    {
        return in_array($vaultId, $this->vault_permissions ?? []) ||
            in_array('*', $this->vault_permissions ?? []);
    }

    public function hasOperationPermission(string $operation): bool
    {
        return in_array($operation, $this->operation_permissions ?? []) ||
            in_array('*', $this->operation_permissions ?? []);
    }

    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->ip_whitelist)) {
            return true; // No restrictions
        }

        return in_array($ip, $this->ip_whitelist);
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

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
}
