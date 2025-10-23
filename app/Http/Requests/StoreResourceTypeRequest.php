<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|integer|min:1',
            'timetable_id' => 'nullable|integer|exists:timetables,id',
            'type' => 'required|string|max:63',
            'name' => 'required|string|max:127',
            'description' => 'nullable|string|max:255',
            'options' => 'nullable|array',
            'resource_config' => 'nullable|array',
        ];
    }
}
