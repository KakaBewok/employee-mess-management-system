<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')->id;

        return [
            'employee_code' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('employees', 'employee_code')->ignore($employeeId),
            ],
            'name' => ['sometimes', 'required', 'string'],
            'department' => ['sometimes', 'required'],
            'status' => ['sometimes', 'required'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('employee_code')) {
            $this->merge([
                'employee_code' => trim(strtoupper($this->employee_code)),
            ]);
        }
    }
}

