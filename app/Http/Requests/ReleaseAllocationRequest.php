<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReleaseAllocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allocation = $this->route('allocation');
            
            // Check if allocation exists
            if (!$allocation) {
                $validator->errors()->add(
                    'allocation',
                    'Allocation not found.'
                );
                return;
            }

            // Check if allocation is already released
            if ($allocation->isReleased()) {
                $validator->errors()->add(
                    'allocation',
                    'This allocation has already been released on ' . $allocation->released_at->format('Y-m-d H:i:s') . '.'
                );
            }
        });
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'notes' => 'notes',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge([
                'notes' => $this->notes ? trim($this->notes) : null,
            ]);
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
