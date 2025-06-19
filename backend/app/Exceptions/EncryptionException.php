<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EncryptionException extends Exception
{
    public function render(Request $request)
    {
        return response()->json([
            'error' => [
                'message' => 'Encryption operation failed',
                'code' => 'ENCRYPTION_ERROR',
                'type' => 'encryption_error',
            ],
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
