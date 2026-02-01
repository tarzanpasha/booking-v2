<?php

namespace App\Http\Requests;

use App\Enums\TimetableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(TimetableType::values())],
            'schedule' => 'sometimes|array',
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
}
