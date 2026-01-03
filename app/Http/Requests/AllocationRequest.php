<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Models\Employee;
use App\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Exceptions\HttpResponseException;

class AllocationRequest extends FormRequest
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
            'room_id' => [
                'required',
                'integer',
                'exists:rooms,id',
            ],
            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
                'required_without:guest_id',
            ],
            'guest_id' => [
                'nullable',
                'integer',
                'exists:guests,id',
                'required_without:employee_id',
            ],
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
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function ($validator) {
            // Rule 1: Check that ONLY ONE of employee_id or guest_id is provided
            $hasEmployee = $this->filled('employee_id') && !empty($this->employee_id);
            $hasGuest = $this->filled('guest_id') && !empty($this->guest_id);

            if ($hasEmployee && $hasGuest) {
                $validator->errors()->add(
                    'employee_id',
                    'Cannot allocate both employee and guest to the same allocation. Please select only one.'
                );
                $validator->errors()->add(
                    'guest_id',
                    'Cannot allocate both employee and guest to the same allocation. Please select only one.'
                );
                return;
            }

            if (!$hasEmployee && !$hasGuest) {
                $validator->errors()->add(
                    'employee_id',
                    'Either employee or guest must be selected for allocation.'
                );
                $validator->errors()->add(
                    'guest_id',
                    'Either employee or guest must be selected for allocation.'
                );
                return;
            }

            // Rule 2: Validate room exists and has capacity
            if ($this->filled('room_id')) {
                $room = Room::find($this->room_id);
                
                if (!$room) {
                    $validator->errors()->add(
                        'room_id',
                        'Selected room does not exist.'
                    );
                    return;
                }

                if (!$room->isAvailable()) {
                    $validator->errors()->add(
                        'room_id',
                        "Room {$room->room_code} is at full capacity ({$room->capacity} person(s)). Current occupancy: {$room->getCurrentOccupancy()}. Please select another room."
                    );
                }
            }

            // Rule 3: Check if employee is valid and doesn't have active allocation
            if ($hasEmployee) {
                $employee = Employee::find($this->employee_id);
                
                if (!$employee) {
                    $validator->errors()->add(
                        'employee_id',
                        'Selected employee does not exist.'
                    );
                    return;
                }

                // Check employee status
                if (!$employee->isActive()) {
                    $validator->errors()->add(
                        'employee_id',
                        "Employee {$employee->name} ({$employee->employee_code}) is inactive and cannot be allocated to a room. Please activate the employee first."
                    );
                }

                // Check for existing active allocation
                if ($employee->hasActiveAllocation()) {
                    $currentRoom = $employee->getCurrentRoom();
                    $validator->errors()->add(
                        'employee_id',
                        "Employee {$employee->name} ({$employee->employee_code}) is already allocated to room {$currentRoom->room_code}. Please release the current allocation first."
                    );
                }
            }

            // Rule 4: Check if guest is valid and doesn't have active allocation
            if ($hasGuest) {
                $guest = Guest::find($this->guest_id);
                
                if (!$guest) {
                    $validator->errors()->add(
                        'guest_id',
                        'Selected guest does not exist.'
                    );
                    return;
                }

                // Check for existing active allocation
                if ($guest->hasActiveAllocation()) {
                    $currentRoom = $guest->getCurrentRoom();
                    $validator->errors()->add(
                        'guest_id',
                        "Guest {$guest->name} is already allocated to room {$currentRoom->room_code}. Please release the current allocation first."
                    );
                }
            }
        });
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Please select a room.',
            'room_id.integer' => 'Invalid room selection.',
            'room_id.exists' => 'Selected room does not exist.',
            
            'employee_id.integer' => 'Invalid employee selection.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'employee_id.required_without' => 'Either employee or guest must be selected.',
            
            'guest_id.integer' => 'Invalid guest selection.',
            'guest_id.exists' => 'Selected guest does not exist.',
            'guest_id.required_without' => 'Either employee or guest must be selected.',
            
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'room_id' => 'room',
            'employee_id' => 'employee',
            'guest_id' => 'guest',
            'notes' => 'notes',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Convert empty strings to null for proper validation
        if ($this->has('employee_id')) {
            $data['employee_id'] = $this->employee_id === '' || $this->employee_id === '0' ? null : $this->employee_id;
        }

        if ($this->has('guest_id')) {
            $data['guest_id'] = $this->guest_id === '' || $this->guest_id === '0' ? null : $this->guest_id;
        }

        if ($this->has('notes')) {
            $data['notes'] = $this->notes ? trim($this->notes) : null;
        }

        $this->merge($data);
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(ValidatorContract $validator)
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
