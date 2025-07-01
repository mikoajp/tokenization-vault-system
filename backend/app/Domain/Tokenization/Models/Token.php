<?php

namespace App\Domain\Tokenization\Models;

use App\Domain\Tokenization\ValueObjects\TokenId;
use App\Domain\Tokenization\ValueObjects\TokenStatus;
use App\Domain\Tokenization\ValueObjects\TokenType;
use App\Domain\Tokenization\ValueObjects\TokenValue;
use App\Domain\Tokenization\Events\TokenCreated;
use App\Domain\Tokenization\Events\TokenRevoked;
use App\Domain\Tokenization\Events\TokenExpired;
use App\Domain\Tokenization\Events\TokenCompromised;
use App\Domain\Tokenization\Events\TokenUsed;
use App\Domain\Vault\ValueObjects\VaultId;
use App\Shared\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Token extends Model
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'vault_id',
        'token_value',
        'format_preserved_token',
        'token_type',
        'metadata',
        'expires_at',
        'key_version',
        'status',
        'encrypted_data',
        'data_hash',
        'checksum',
        'usage_count',
        'last_used_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    protected $hidden = [
        'encrypted_data',
        'data_hash',
        'checksum'
    ];

    protected $dispatchesEvents = [
        'created' => TokenCreated::class,
    ];

    // Domain Methods

    public function getId(): TokenId
    {
        return new TokenId($this->id);
    }

    public function getVaultId(): VaultId
    {
        return new VaultId($this->vault_id);
    }

    public function getStatus(): TokenStatus
    {
        return new TokenStatus($this->status);
    }

    public function getType(): TokenType
    {
        return new TokenType($this->token_type);
    }

    public function getTokenValue(): TokenValue
    {
        return new TokenValue($this->token_value);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isActive(): bool
    {
        return $this->getStatus()->isActive() && !$this->isExpired();
    }

    public function isUsable(): bool
    {
        return $this->isActive();
    }

    public function canBeRevoked(): bool
    {
        return $this->getStatus()->isActive();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function willExpireWithin(int $hours): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isBefore(now()->addHours($hours));
    }

    public function recordUsage(): void
    {
        if (!$this->isUsable()) {
            throw new \App\Domain\Tokenization\Exceptions\TokenNotUsableException(
                "Token {$this->token_value} is not usable"
            );
        }

        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);

        event(new TokenUsed($this));
    }

    public function revoke(string $reason = null): void
    {
        if (!$this->canBeRevoked()) {
            throw new \App\Domain\Tokenization\Exceptions\TokenCannotBeRevokedException(
                "Token {$this->token_value} cannot be revoked"
            );
        }

        $this->update([
            'status' => TokenStatus::revoked()->getValue(),
            'metadata' => array_merge($this->metadata ?? [], [
                'revoked_at' => now()->toISOString(),
                'revocation_reason' => $reason
            ])
        ]);

        event(new TokenRevoked($this, $reason));
    }

    public function expire(): void
    {
        $this->update([
            'status' => TokenStatus::expired()->getValue(),
            'expires_at' => now()
        ]);

        event(new TokenExpired($this));
    }

    public function markCompromised(string $reason = null): void
    {
        $this->update([
            'status' => TokenStatus::compromised()->getValue(),
            'metadata' => array_merge($this->metadata ?? [], [
                'compromised_at' => now()->toISOString(),
                'compromise_reason' => $reason
            ])
        ]);

        event(new TokenCompromised($this, $reason));
    }

    public function extendExpiration(int $days): void
    {
        if (!$this->isActive()) {
            throw new \App\Domain\Tokenization\Exceptions\TokenNotActiveException(
                "Cannot extend expiration of inactive token"
            );
        }

        $newExpiration = $this->expires_at 
            ? $this->expires_at->addDays($days)
            : now()->addDays($days);

        $this->update(['expires_at' => $newExpiration]);
    }

    public function updateMetadata(array $metadata): void
    {
        $this->update([
            'metadata' => array_merge($this->metadata ?? [], $metadata)
        ]);
    }

    public function getUsageStatistics(): array
    {
        return [
            'usage_count' => $this->usage_count,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'days_since_creation' => $this->created_at->diffInDays(now()),
            'days_since_last_use' => $this->last_used_at?->diffInDays(now()),
            'expires_at' => $this->expires_at?->toISOString(),
            'days_until_expiration' => $this->expires_at?->diffInDays(now()),
        ];
    }

    // Relationships
    public function vault(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Vault\Models\Vault::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(\App\Domain\Audit\Models\AuditLog::class);
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

    public function scopeUsable($query)
    {
        return $query->active()->notExpired();
    }

    public function scopeByVault($query, string $vaultId)
    {
        return $query->where('vault_id', $vaultId);
    }

    public function scopeByTokenValue($query, string $tokenValue)
    {
        return $query->where('token_value', $tokenValue);
    }

    public function scopeByType($query, string $tokenType)
    {
        return $query->where('token_type', $tokenType);
    }

    public function scopeExpiringWithin($query, int $hours)
    {
        return $query->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()->addHours($hours));
    }

    public function scopeRecentlyUsed($query, int $days = 30)
    {
        return $query->where('last_used_at', '>=', now()->subDays($days));
    }

    public function scopeUnused($query, int $days = 90)
    {
        return $query->where(function ($q) use ($days) {
            $q->whereNull('last_used_at')
              ->orWhere('last_used_at', '<', now()->subDays($days));
        });
    }
}