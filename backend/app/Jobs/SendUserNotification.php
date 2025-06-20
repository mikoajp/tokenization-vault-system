<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendUserNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 3;

    public function __construct(
        private string $userId,
        private string $title,
        private string $message
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        try {
            Log::info('Sending user notification', [
                'user_id' => $this->userId,
                'title' => $this->title,
            ]);

            // Tutaj implementacja systemu powiadomień in-app
            // Na przykład przez database notifications, websockets, itp.

            // $user = User::find($this->userId);
            // $user->notify(new ComplianceReportNotification($this->title, $this->message));

        } catch (\Exception $e) {
            Log::error('Failed to send user notification', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
