<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancelled_by' => 'sometimes|in:client,admin',
            'reason' => 'sometimes|string|max:255',
            'booker' => 'sometimes',
        ];
    }
}
