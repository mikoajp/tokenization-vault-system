<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TokenizationException extends Exception
{
    protected $errorCode;
    protected $context;

    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null, string $errorCode = 'TOKENIZATION_ERROR', array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function render(Request $request)
    {
        return response()->json([
            'error' => [
                'message' => $this->getMessage(),
                'code' => $this->errorCode,
                'type' => 'tokenization_error',
                'context' => $this->context,
            ],
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ], Response::HTTP_BAD_REQUEST);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
