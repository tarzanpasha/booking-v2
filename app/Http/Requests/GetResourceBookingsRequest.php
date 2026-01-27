<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetResourceBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => 'required|date',
            'to' => 'required|date|after:from'
        ];
    }
}
