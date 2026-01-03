<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_code' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('rooms', 'room_code'),
            ],
            'capacity' => ['required', 'integer', Rule::in([1, 2])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('room_code')) {
            $this->merge([
                'room_code' => strtoupper(trim($this->room_code)),
            ]);
        }
    }
}

