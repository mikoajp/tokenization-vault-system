<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Shared\Traits\AuditableTrait;

class VaultKey extends Model
{
    use HasFactory, HasUuids, AuditableTrait;

    protected $fillable = [
        'vault_id', 'key_version', 'status',
        'activated_at', 'retired_at'
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_key', 'key_hash'
    ];

    // Relationships
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForVault($query, $vaultId)
    {
        return $query->where('vault_id', $vaultId);
    }

    // Methods
    public function retire(): void
    {
        $this->update([
            'status' => 'retired',
            'retired_at' => now()
        ]);
    }

    public function markCompromised(): void
    {
        $this->update([
            'status' => 'compromised',
            'retired_at' => now()
        ]);
    }
}