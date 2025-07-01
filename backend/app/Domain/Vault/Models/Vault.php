<?php

namespace App\Domain\Vault\Models;

use App\Domain\Vault\ValueObjects\VaultId;
use App\Domain\Vault\ValueObjects\VaultStatus;
use App\Domain\Vault\ValueObjects\DataType;
use App\Domain\Vault\ValueObjects\EncryptionConfig;
use App\Domain\Vault\Events\VaultCreated;
use App\Domain\Vault\Events\VaultKeyRotated;
use App\Domain\Vault\Events\VaultStatusChanged;
use App\Models\VaultKey;
use App\Shared\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

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

    protected $dispatchesEvents = [
        'created' => VaultCreated::class,
    ];

    // Domain Methods

    public function getId(): VaultId
    {
        return new VaultId($this->id);
    }

    public function getStatus(): VaultStatus
    {
        return new VaultStatus($this->status);
    }

    public function getDataType(): DataType
    {
        return new DataType($this->data_type);
    }

    public function getEncryptionConfig(): EncryptionConfig
    {
        return new EncryptionConfig(
            $this->encryption_algorithm,
            $this->encryption_key_reference
        );
    }

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
        return $this->getStatus()->isActive() && !$this->hasReachedTokenLimit();
    }

    public function incrementTokenCount(): void
    {
        if ($this->hasReachedTokenLimit()) {
            throw new \App\Domain\Vault\Exceptions\VaultTokenLimitExceededException(
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
        
        event(new VaultKeyRotated($this));
    }

    public function changeStatus(VaultStatus $newStatus): void
    {
        $oldStatus = $this->getStatus();
        $this->update(['status' => $newStatus->getValue()]);
        
        event(new VaultStatusChanged($this, $oldStatus, $newStatus));
    }

    public function activate(): void
    {
        $this->changeStatus(VaultStatus::active());
    }

    public function deactivate(): void
    {
        $this->changeStatus(VaultStatus::inactive());
    }

    public function archive(): void
    {
        $this->changeStatus(VaultStatus::archived());
    }

    // Relationships
    public function tokens(): HasMany
    {
        return $this->hasMany(\App\Domain\Tokenization\Models\Token::class);
    }

    public function vaultKeys(): HasMany
    {
        return $this->hasMany(VaultKey::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(\App\Domain\Audit\Models\AuditLog::class);
    }

    public function retentionPolicies(): HasMany
    {
        return $this->hasMany(\App\Domain\Compliance\Models\DataRetentionPolicy::class);
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
}