<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roomId = $this->route('room')->id;

        return [
            'room_code' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('rooms', 'room_code')->ignore($roomId),
            ],
            'capacity' => [
                'sometimes',
                'required',
                'integer',
                Rule::in([1, 2]),
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('room_code')) {
            $this->merge([
                'room_code' => trim(strtoupper($this->room_code)),
            ]);
        }
    }
}

