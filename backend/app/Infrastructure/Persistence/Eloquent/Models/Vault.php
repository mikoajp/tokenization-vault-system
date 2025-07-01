<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Shared\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vault extends Model
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'name', 
        'description', 
        'data_type', 
        'status',
        'encryption_algorithm', 
        'max_tokens',
        'allowed_operations', 
        'access_restrictions',
        'retention_days', 
        'key_rotation_interval_days'
    ];

    protected $casts = [
        'allowed_operations' => 'array',
        'access_restrictions' => 'array',
        'last_key_rotation' => 'datetime',
    ];

    protected $hidden = [
        'encryption_key_reference'
    ];

    // Relationships
    public function vaultKeys(): HasMany
    {
        return $this->hasMany(VaultKey::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function retentionPolicies(): HasMany
    {
        return $this->hasMany(DataRetentionPolicy::class);
    }

    public function getActiveKey(): ?VaultKey
    {
        return $this->vaultKeys()
            ->where('status', 'active')
            ->latest('activated_at')
            ->first();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByDataType($query, string $dataType)
    {
        return $query->where('data_type', $dataType);
    }

    public function scopeNeedingKeyRotation($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_key_rotation')
              ->orWhereRaw('DATE_ADD(last_key_rotation, INTERVAL key_rotation_interval_days DAY) <= NOW()');
        });
    }

    // Business logic methods
    public function isOperationAllowed(string $operation): bool
    {
        return in_array($operation, $this->allowed_operations ?? []);
    }

    public function hasReachedTokenLimit(): bool
    {
        return $this->current_token_count >= $this->max_tokens;
    }

    public function needsKeyRotation(): bool
    {
        if (!$this->last_key_rotation) {
            return true;
        }

        $rotationDue = $this->last_key_rotation->addDays($this->key_rotation_interval_days);
        return now() >= $rotationDue;
    }

    public function canAcceptNewTokens(): bool
    {
        return $this->status === 'active' && !$this->hasReachedTokenLimit();
    }

    public function incrementTokenCount(): void
    {
        if ($this->hasReachedTokenLimit()) {
            throw new \Exception(
                "Vault {$this->name} has reached its token limit of {$this->max_tokens}"
            );
        }

        $this->increment('current_token_count');
    }

    public function decrementTokenCount(): void
    {
        if ($this->current_token_count > 0) {
            $this->decrement('current_token_count');
        }
    }

    public function rotateKey(): void
    {
        $this->update(['last_key_rotation' => now()]);
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }
}