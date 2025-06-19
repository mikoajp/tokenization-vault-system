<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\AuditableTrait;

class DataRetentionPolicy extends Model
{
    use HasFactory, HasUuids, AuditableTrait;

    protected $fillable = [
        'vault_id', 'policy_name', 'retention_days', 'action_after_retention',
        'auto_execute', 'cron_schedule', 'last_executed_at', 'next_execution_at',
        'status', 'execution_log'
    ];

    protected $casts = [
        'auto_execute' => 'boolean',
        'last_executed_at' => 'datetime',
        'next_execution_at' => 'datetime',
        'execution_log' => 'array',
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

    public function scopeDueForExecution($query)
    {
        return $query->where('status', 'active')
            ->where('auto_execute', true)
            ->where('next_execution_at', '<=', now());
    }

    // Methods
    public function calculateNextExecution(): void
    {
        $this->update([
            'next_execution_at' => now()->addDay()
        ]);
    }

    public function logExecution(array $result): void
    {
        $log = $this->execution_log ?? [];
        $log[] = [
            'executed_at' => now()->toISOString(),
            'result' => $result,
        ];

        $log = array_slice($log, -10);

        $this->update([
            'last_executed_at' => now(),
            'execution_log' => $log
        ]);

        $this->calculateNextExecution();
    }
}
