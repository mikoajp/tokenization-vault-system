<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class TokenizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vault_id' => 'required|uuid|exists:vaults,id',
            'data' => 'required|string|min:1|max:10000',
            'metadata' => 'nullable|array',
            'metadata.expires_in_days' => 'nullable|integer|min:1|max:3650',
            'metadata.category' => 'nullable|string|max:100',
            'metadata.source_system' => 'nullable|string|max:100',
            'format_preserving' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'vault_id.required' => 'Vault ID is required',
            'vault_id.uuid' => 'Vault ID must be a valid UUID',
            'vault_id.exists' => 'Specified vault does not exist',
            'data.required' => 'Data to tokenize is required',
            'data.string' => 'Data must be a string',
            'data.min' => 'Data cannot be empty',
            'data.max' => 'Data is too large (maximum 10,000 characters)',
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
