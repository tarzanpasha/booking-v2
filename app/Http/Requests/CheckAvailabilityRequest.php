<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resource_id' => 'required|exists:resources,id',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'slots' => 'sometimes|integer|min:1'
        ];
    }
}
