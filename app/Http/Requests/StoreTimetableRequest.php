<?php

namespace App\Http\Requests;

use App\Enums\TimetableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|integer|min:1',
            'type' => ['required', 'string', Rule::in(TimetableType::values())],
            'schedule' => 'required|array',
            'schedule.days' => 'required_if:type,static|array',
            'schedule.days.*.working_hours' => 'required_if:type,static|array',
            'schedule.days.*.working_hours.start' => 'required_if:type,static|date_format:H:i',
            'schedule.days.*.working_hours.end' => 'required_if:type,static|date_format:H:i',
            'schedule.days.*.breaks' => 'sometimes|array',
            'schedule.days.*.breaks.*.start' => 'required_with:schedule.days.*.breaks|date_format:H:i',
            'schedule.days.*.breaks.*.end' => 'required_with:schedule.days.*.breaks|date_format:H:i',
            'schedule.holidays' => 'sometimes|array',
            'schedule.holidays.*' => 'string|regex:/^\d{2}-\d{2}$/',
            'schedule.dates' => 'required_if:type,dynamic|array',
            'schedule.dates.*.working_hours' => 'required_if:type,dynamic|array',
            'schedule.dates.*.working_hours.start' => 'required_if:type,dynamic|date_format:H:i',
            'schedule.dates.*.working_hours.end' => 'required_if:type,dynamic|date_format:H:i',
            'schedule.dates.*.breaks' => 'sometimes|array',
            'schedule.dates.*.breaks.*.start' => 'required_with:schedule.dates.*.breaks|date_format:H:i',
            'schedule.dates.*.breaks.*.end' => 'required_with:schedule.dates.*.breaks|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'schedule.holidays.*.regex' => 'Holiday format must be MM-DD',
            'schedule.days.*.working_hours.start.date_format' => 'Start time must be in HH:MM format',
            'schedule.days.*.working_hours.end.date_format' => 'End time must be in HH:MM format',
            'schedule.dates.*.working_hours.start.date_format' => 'Start time must be in HH:MM format',
            'schedule.dates.*.working_hours.end.date_format' => 'End time must be in HH:MM format',
        ];
    }
}
