<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RoomRequest extends FormRequest
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
        $roomId = $this->route('room')?->id;

        return [
            'room_code' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('rooms', 'room_code')
                    ->ignore($roomId)
                    ->whereNull('deleted_at'),
            ],
            'capacity' => [
                'required',
                'integer',
                'min:1',
                'max:2',
                Rule::in([Room::CAPACITY_ONE, Room::CAPACITY_TWO]),
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
            'room_code.required' => 'Room code is required.',
            'room_code.min' => 'Room code must be at least 2 characters.',
            'room_code.max' => 'Room code cannot exceed 50 characters.',
            'room_code.unique' => 'This room code is already in use.',
            'room_code.regex' => 'Room code must contain only uppercase letters, numbers, hyphens, and underscores (A-Z, 0-9, -, _).',
            
            'capacity.required' => 'Room capacity is required.',
            'capacity.integer' => 'Room capacity must be a number.',
            'capacity.min' => 'Room capacity must be at least 1 person.',
            'capacity.max' => 'Room capacity cannot exceed 2 persons.',
            'capacity.in' => 'Room capacity must be either 1 or 2 persons.',
            
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'room_code' => 'room code',
            'capacity' => 'capacity',
            'notes' => 'notes',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('room_code')) {
            $data['room_code'] = trim(strtoupper($this->room_code));
        }

        if ($this->has('capacity')) {
            $data['capacity'] = (int) $this->capacity;
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
            // Additional validation for updating capacity
            if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                $room = $this->route('room');
                
                if ($room && $this->has('capacity')) {
                    $currentOccupancy = $room->getCurrentOccupancy();
                    $newCapacity = $this->capacity;
                    
                    if ($newCapacity < $currentOccupancy) {
                        $validator->errors()->add(
                            'capacity',
                            "Cannot reduce capacity to {$newCapacity}. Current occupancy is {$currentOccupancy}. Please release some allocations first."
                        );
                    }
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
