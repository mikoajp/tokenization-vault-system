<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendReportFailedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct(
        private string          $email,
        private readonly string $reportId,
        private readonly string $errorMessage
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        try {
            Log::info('Sending report failed email', [
                'email' => $this->email,
                'report_id' => $this->reportId,
            ]);

            Mail::send('emails.compliance-report-failed', [
                'reportId' => $this->reportId,
                'errorMessage' => $this->errorMessage,
            ], function ($message) {
                $message->to($this->email)
                    ->subject("Compliance Report Generation Failed");
            });

        } catch (\Exception $e) {
            Log::error('Failed to send report failed email', [
                'email' => $this->email,
                'report_id' => $this->reportId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
