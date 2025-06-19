<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class DetokenizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vault_id' => 'required|uuid|exists:vaults,id',
            'token' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'vault_id.required' => 'Vault ID is required',
            'vault_id.uuid' => 'Vault ID must be a valid UUID',
            'vault_id.exists' => 'Specified vault does not exist',
            'token.required' => 'Token is required',
            'token.string' => 'Token must be a string',
            'token.regex' => 'Token format is invalid',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'message' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'type' => 'validation_error',
                    'details' => $validator->errors(),
                ],
                'request_id' => $this->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
