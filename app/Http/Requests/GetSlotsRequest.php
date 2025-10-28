<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|date',
            'count' => 'sometimes|integer|min:1|max:50',
            'only_today' => 'sometimes|boolean'
        ];
    }
}
