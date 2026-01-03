<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmployeeRequest extends FormRequest
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
        $employeeId = $this->route('employee')?->id;

        return [
            'employee_code' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('employees', 'employee_code')
                    ->ignore($employeeId)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[A-Za-z\s]+$/',
            ],
            'department' => [
                'required',
                'string',
                Rule::in(array_keys(Employee::DEPARTMENTS)),
            ],
            'status' => [
                'required',
                'string',
                Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE]),
            ],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'employee_code.required' => 'Employee code is required.',
            'employee_code.min' => 'Employee code must be at least 3 characters.',
            'employee_code.max' => 'Employee code cannot exceed 50 characters.',
            'employee_code.unique' => 'This employee code is already in use.',
            'employee_code.regex' => 'Employee code must contain only uppercase letters, numbers, hyphens, and underscores (A-Z, 0-9, -, _).',
            
            'name.required' => 'Employee name is required.',
            'name.min' => 'Employee name must be at least 2 characters.',
            'name.max' => 'Employee name cannot exceed 255 characters.',
            'name.regex' => 'Employee name must contain only letters (A-Z) and spaces.',
            
            'department.required' => 'Department is required.',
            'department.in' => 'Selected department is invalid. Must be one of: HR, Finance, Produksi, Sarana, Safety.',
            
            'status.required' => 'Status is required.',
            'status.in' => 'Selected status is invalid. Must be either active or inactive.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'employee_code' => 'employee code',
            'name' => 'name',
            'department' => 'department',
            'status' => 'status',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace and normalize data
        $data = [];

        if ($this->has('employee_code')) {
            $data['employee_code'] = trim(strtoupper($this->employee_code));
        }

        if ($this->has('name')) {
            $data['name'] = trim($this->name);
        }

        if ($this->has('department')) {
            $data['department'] = trim($this->department);
        }

        if ($this->has('status')) {
            $data['status'] = trim($this->status);
        }

        $this->merge($data);
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
