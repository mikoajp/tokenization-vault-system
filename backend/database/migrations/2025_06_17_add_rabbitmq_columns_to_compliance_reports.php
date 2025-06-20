<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_reports', function (Blueprint $table) {
            $table->integer('progress')->default(0)->after('status');
            $table->string('status_message')->nullable()->after('progress');
            $table->string('download_url')->nullable()->after('file_path');
            $table->bigInteger('file_size')->nullable()->after('file_hash');

            $table->string('email')->nullable()->after('generated_by');

            $table->json('request_parameters')->nullable()->after('filters_applied');

            $table->timestamp('started_at')->nullable()->after('expires_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');

            $table->text('error_message')->nullable()->after('completed_at');

            $table->dropColumn('status');
        });

        Schema::table('compliance_reports', function (Blueprint $table) {
            $table->enum('status', [
                'queued',           // W kolejce do przetwarzania
                'generating',       // Obecnie generowany (stary status)
                'processing',       // Alternatywna nazwa dla generating
                'completed',        // Zakończony pomyślnie
                'failed',           // Błąd podczas generowania
                'archived'          // Zarchiwizowany
            ])->default('queued')->after('period_end');
        });
        Schema::table('compliance_reports', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
            $table->index(['progress', 'status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_reports', function (Blueprint $table) {
            $table->dropColumn([
                'progress',
                'status_message',
                'download_url',
                'file_size',
                'email',
                'request_parameters',
                'started_at',
                'completed_at',
                'error_message'
            ]);

            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['progress', 'status']);
            $table->dropIndex(['email']);

            $table->dropColumn('status');
        });

        Schema::table('compliance_reports', function (Blueprint $table) {
            $table->enum('status', ['generating', 'completed', 'failed', 'archived'])
                ->default('generating')
                ->after('period_end');
        });
    }
};
