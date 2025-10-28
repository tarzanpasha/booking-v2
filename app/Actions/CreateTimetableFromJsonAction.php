<?php

namespace App\Actions;

use App\Models\Timetable;
use App\Models\Company;
use App\Enums\TimetableType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateTimetableFromJsonAction
{
    public function execute(int $companyId, array $jsonData, string $type = 'static'): Timetable
    {
        // Очищаем данные от null значений
        $cleanedData = $this->cleanScheduleData($jsonData, $type);

        // Валидация данных
        $this->validateJsonData($cleanedData, $type);

        // Проверяем существование компании
        $company = Company::find($companyId);
        if (!$company) {
            throw new \Exception("Company with ID {$companyId} not found");
        }

        // Создаем расписание
        return Timetable::create([
            'company_id' => $companyId,
            'type' => $type,
            'schedule' => $cleanedData,
        ]);
    }

    private function cleanScheduleData(array $data, string $type): array
    {
        if ($type === TimetableType::STATIC->value) {
            // Удаляем дни с null значениями
            if (isset($data['days']) && is_array($data['days'])) {
                $data['days'] = array_filter($data['days'], function ($day) {
                    return $day !== null;
                });
            }
        } else {
            // Удаляем даты с null значениями
            if (isset($data['dates']) && is_array($data['dates'])) {
                $data['dates'] = array_filter($data['dates'], function ($date) {
                    return $date !== null;
                });
            }
        }

        return $data;
    }

    private function validateJsonData(array $data, string $type): void
    {
        $rules = [];

        if ($type === TimetableType::STATIC->value) {
            $rules = [
                'days' => 'required|array',
                'days.*.working_hours' => 'required|array',
                'days.*.working_hours.start' => 'required|date_format:H:i',
                'days.*.working_hours.end' => 'required|date_format:H:i',
                'days.*.breaks' => 'sometimes|array',
                'days.*.breaks.*.start' => 'required_with:days.*.breaks|date_format:H:i',
                'days.*.breaks.*.end' => 'required_with:days.*.breaks|date_format:H:i',
                'holidays' => 'sometimes|array',
                'holidays.*' => 'string|regex:/^\d{2}-\d{2}$/',
            ];
        } else {
            $rules = [
                'dates' => 'required|array',
                'dates.*.working_hours' => 'required|array',
                'dates.*.working_hours.start' => 'required|date_format:H:i',
                'dates.*.working_hours.end' => 'required|date_format:H:i',
                'dates.*.breaks' => 'sometimes|array',
                'dates.*.breaks.*.start' => 'required_with:dates.*.breaks|date_format:H:i',
                'dates.*.breaks.*.end' => 'required_with:dates.*.breaks|date_format:H:i',
            ];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
