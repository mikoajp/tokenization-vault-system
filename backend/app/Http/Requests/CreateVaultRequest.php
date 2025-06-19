<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class CreateVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:100|unique:vaults,name',
            'description' => 'nullable|string|max:500',
            'data_type' => 'required|in:card,ssn,bank_account,custom',
            'max_tokens' => 'nullable|integer|min:1000|max:10000000',
            'allowed_operations' => 'nullable|array',
            'allowed_operations.*' => 'in:tokenize,detokenize,search,bulk_tokenize,bulk_detokenize',
            'access_restrictions' => 'nullable|array',
            'access_restrictions.ip_whitelist' => 'nullable|array',
            'access_restrictions.ip_whitelist.*' => 'ip',
            'retention_days' => 'nullable|integer|min:30|max:3650',
            'key_rotation_interval_days' => 'nullable|integer|min:30|max:1095',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vault name is required',
            'name.min' => 'Vault name must be at least 3 characters',
            'name.max' => 'Vault name cannot exceed 100 characters',
            'name.unique' => 'A vault with this name already exists',
            'data_type.required' => 'Data type is required',
            'data_type.in' => 'Data type must be: card, ssn, bank_account, or custom',
            'max_tokens.min' => 'Maximum tokens must be at least 1,000',
            'max_tokens.max' => 'Maximum tokens cannot exceed 10,000,000',
            'allowed_operations.*.in' => 'Invalid operation specified',
            'retention_days.min' => 'Retention period must be at least 30 days',
            'retention_days.max' => 'Retention period cannot exceed 10 years',
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

    protected function prepareForValidation()
    {
        $this->merge([
            'max_tokens' => $this->max_tokens ?? 1000000,
            'allowed_operations' => $this->allowed_operations ?? ['tokenize', 'detokenize'],
            'retention_days' => $this->retention_days ?? 2555, // 7 years
            'key_rotation_interval_days' => $this->key_rotation_interval_days ?? 365,
        ]);
    }
}
