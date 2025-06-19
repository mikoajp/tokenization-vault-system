<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class BulkTokenizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vault_id' => 'required|uuid|exists:vaults,id',
            'data_items' => 'required|array|min:1|max:1000',
            'data_items.*' => 'required|string|min:1|max:10000',
            'metadata' => 'nullable|array',
            'metadata.batch_id' => 'nullable|string|max:100',
            'metadata.source_system' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'data_items.required' => 'Data items array is required',
            'data_items.min' => 'At least one data item is required',
            'data_items.max' => 'Maximum 1000 items allowed per batch',
            'data_items.*.required' => 'Each data item is required',
            'data_items.*.string' => 'Each data item must be a string',
            'data_items.*.min' => 'Data items cannot be empty',
            'data_items.*.max' => 'Data item is too large (maximum 10,000 characters)',
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
