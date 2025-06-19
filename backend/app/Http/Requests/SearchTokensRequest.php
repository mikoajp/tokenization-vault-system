<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class SearchTokensRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vault_id' => 'required|uuid|exists:vaults,id',
            'metadata' => 'nullable|array',
            'token_type' => 'nullable|in:random,format_preserving,sequential',
            'created_after' => 'nullable|date',
            'created_before' => 'nullable|date|after_or_equal:created_after',
            'limit' => 'nullable|integer|min:1|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'vault_id.required' => 'Vault ID is required',
            'vault_id.uuid' => 'Vault ID must be a valid UUID',
            'vault_id.exists' => 'Specified vault does not exist',
            'token_type.in' => 'Token type must be: random, format_preserving, or sequential',
            'created_after.date' => 'Created after must be a valid date',
            'created_before.date' => 'Created before must be a valid date',
            'created_before.after_or_equal' => 'Created before must be after or equal to created after',
            'limit.min' => 'Limit must be at least 1',
            'limit.max' => 'Limit cannot exceed 1000',
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
