<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_start' => 'required|date',
            'new_end' => 'required|date|after:new_start',
            'requested_by' => 'sometimes|in:client,admin'
        ];
    }
}
