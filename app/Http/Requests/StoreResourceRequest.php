<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
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
            'resource_type_id' => 'required|integer|exists:resource_types,id',
            'options' => 'nullable|array',
            'payload' => 'nullable|array',
            'resource_config' => 'nullable|array',
        ];
    }
}
