<?php

namespace App\Http\Requests;

use App\Enums\TimetableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|integer|exists:companies,id',
            'type' => ['required', 'string', Rule::in(TimetableType::values())],
            'schedule_data' => 'required_without:schedule_file|array',
            'schedule_file' => 'required_without:schedule_data|file|mimes:json|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_file.mimes' => 'The file must be a JSON file',
            'schedule_file.max' => 'The file size must not exceed 10MB',
        ];
    }
}
