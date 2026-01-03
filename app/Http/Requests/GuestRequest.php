<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Carbon\Carbon;

class GuestRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\d\s\-\+\(\)]+$/',
            ],
            'company' => [
                'nullable',
                'string',
                'max:255',
            ],
            'visit_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'checkout_date' => [
                'nullable',
                'date',
                'after:visit_date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Guest name is required.',
            'name.min' => 'Guest name must be at least 2 characters.',
            'name.max' => 'Guest name cannot exceed 255 characters.',
            
            'phone.regex' => 'Phone number format is invalid. Only numbers, spaces, +, -, and parentheses are allowed.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            
            'company.max' => 'Company name cannot exceed 255 characters.',
            
            'visit_date.required' => 'Visit date is required.',
            'visit_date.date' => 'Visit date must be a valid date.',
            'visit_date.after_or_equal' => 'Visit date cannot be in the past.',
            
            'checkout_date.date' => 'Checkout date must be a valid date.',
            'checkout_date.after' => 'Checkout date must be after visit date.',
            
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'name' => 'guest name',
            'phone' => 'phone number',
            'company' => 'company name',
            'visit_date' => 'visit date',
            'checkout_date' => 'checkout date',
            'notes' => 'notes',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name')) {
            $data['name'] = trim($this->name);
        }

        if ($this->has('phone')) {
            $data['phone'] = $this->phone ? trim($this->phone) : null;
        }

        if ($this->has('company')) {
            $data['company'] = $this->company ? trim($this->company) : null;
        }

        if ($this->has('notes')) {
            $data['notes'] = $this->notes ? trim($this->notes) : null;
        }

        $this->merge($data);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate visit duration
            if ($this->has('visit_date') && $this->has('checkout_date') && $this->checkout_date) {
                $visitDate = Carbon::parse($this->visit_date);
                $checkoutDate = Carbon::parse($this->checkout_date);
                $duration = $visitDate->diffInDays($checkoutDate);
                
                // Maximum stay is 30 days
                if ($duration > 30) {
                    $validator->errors()->add(
                        'checkout_date',
                        'Guest stay duration cannot exceed 30 days. Please contact administrator for longer stays.'
                    );
                }
            }
        });
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
