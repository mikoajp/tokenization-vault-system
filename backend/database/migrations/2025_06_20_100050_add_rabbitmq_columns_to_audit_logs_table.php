<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {

            if (!Schema::hasColumn('audit_logs', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->index();
            }

            if (!Schema::hasColumn('audit_logs', 'archive_location')) {
                $table->string('archive_location')->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'compressed_at')) {
                $table->timestamp('compressed_at')->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->index();
            }

            if (!Schema::hasColumn('audit_logs', 'triggered_alerts')) {
                $table->boolean('triggered_alerts')->default(false)->index();
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            try {
                $existingIndexes = collect(Schema::getConnection()->getDoctrineSchemaManager()
                    ->listTableIndexes('audit_logs'))->keys()->toArray();

                if (!in_array('idx_operation_created', $existingIndexes)) {
                    $table->index(['operation', 'created_at'], 'idx_operation_created');
                }

                if (!in_array('idx_vault_operation', $existingIndexes)) {
                    $table->index(['vault_id', 'operation'], 'idx_vault_operation');
                }

                if (!in_array('idx_user_created', $existingIndexes)) {
                    $table->index(['user_id', 'created_at'], 'idx_user_created');
                }

                if (!in_array('idx_ip_created', $existingIndexes)) {
                    $table->index(['ip_address', 'created_at'], 'idx_ip_created');
                }

                if (!in_array('idx_risk_created', $existingIndexes)) {
                    $table->index(['risk_level', 'created_at'], 'idx_risk_created');
                }

                if (!in_array('idx_pci_created', $existingIndexes)) {
                    $table->index(['pci_relevant', 'created_at'], 'idx_pci_created');
                }

                if (!in_array('idx_result_created', $existingIndexes)) {
                    $table->index(['result', 'created_at'], 'idx_result_created');
                }

                if (!in_array('idx_alerts_created', $existingIndexes)) {
                    $table->index(['triggered_alerts', 'created_at'], 'idx_alerts_created');
                }

            } catch (\Exception $e) {
                \Log::warning('Could not create some indexes: ' . $e->getMessage());
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $columnsToRemove = [
                'archived_at',
                'archive_location',
                'compressed_at',
                'processed_at',
                'triggered_alerts'
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('audit_logs', $column)) {
                    $table->dropColumn($column);
                }
            }

            try {
                $table->dropIndex('idx_operation_created');
                $table->dropIndex('idx_vault_operation');
                $table->dropIndex('idx_user_created');
                $table->dropIndex('idx_ip_created');
                $table->dropIndex('idx_risk_created');
                $table->dropIndex('idx_pci_created');
                $table->dropIndex('idx_result_created');
                $table->dropIndex('idx_alerts_created');
            } catch (\Exception $e) {
            }
        });
    }
};
