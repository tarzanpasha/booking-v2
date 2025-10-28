<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
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
            'booker' => 'sometimes|array',
            'booker.external_id' => 'sometimes|string',
            'booker.type' => 'sometimes|string',
            'booker.name' => 'sometimes|string',
            'booker.email' => 'sometimes|email',
            'booker.phone' => 'sometimes|string',
            'booker.metadata' => 'sometimes|array',
            'is_admin' => 'sometimes|boolean'
        ];
    }
}
