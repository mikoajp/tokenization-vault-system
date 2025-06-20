<?php

namespace App\Jobs;

use App\Models\ComplianceReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendReportReadyEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct(
        private string $email,
        private ComplianceReport $report
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        try {
            Log::info('Sending report ready email', [
                'email' => $this->email,
                'report_id' => $this->report->id,
            ]);

            Mail::send('emails.compliance-report-ready', [
                'report' => $this->report,
                'downloadUrl' => $this->report->download_url,
            ], function ($message) {
                $message->to($this->email)
                    ->subject("Compliance Report Ready - {$this->report->report_name}");
            });

        } catch (\Exception $e) {
            Log::error('Failed to send report ready email', [
                'email' => $this->email,
                'report_id' => $this->report->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
