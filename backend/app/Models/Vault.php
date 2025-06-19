<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditableTrait;

class Vault extends Model
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'name', 'description', 'data_type', 'status',
        'encryption_algorithm', 'max_tokens',
        'allowed_operations', 'access_restrictions',
        'retention_days', 'key_rotation_interval_days'
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
    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }

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

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByDataType($query, $dataType)
    {
        return $query->where('data_type', $dataType);
    }

    // Methods
    public function isOperationAllowed(string $operation): bool
    {
        return in_array($operation, $this->allowed_operations ?? []);
    }

    public function incrementTokenCount(): void
    {
        $this->increment('current_token_count');
    }

    public function decrementTokenCount(): void
    {
        $this->decrement('current_token_count');
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

    public function getActiveKey(): ?VaultKey
    {
        return $this->vaultKeys()->where('status', 'active')->latest('activated_at')->first();
    }
}
