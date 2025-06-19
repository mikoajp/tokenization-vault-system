<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VaultException extends Exception
{
    protected $errorCode;
    protected $vaultId;

    public function __construct(string $message = "", string $errorCode = 'VAULT_ERROR', ?string $vaultId = null)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->vaultId = $vaultId;
    }

    public function render(Request $request)
    {
        $statusCode = match($this->errorCode) {
            'VAULT_NOT_FOUND' => Response::HTTP_NOT_FOUND,
            'VAULT_ACCESS_DENIED' => Response::HTTP_FORBIDDEN,
            'VAULT_CAPACITY_EXCEEDED' => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_BAD_REQUEST,
        };

        return response()->json([
            'error' => [
                'message' => $this->getMessage(),
                'code' => $this->errorCode,
                'type' => 'vault_error',
                'vault_id' => $this->vaultId,
            ],
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ], $statusCode);
    }
}
