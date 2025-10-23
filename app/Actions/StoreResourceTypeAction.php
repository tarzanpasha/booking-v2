<?php

namespace App\Actions;

use App\Models\ResourceType;

class StoreResourceTypeAction
{
    public function execute(array $data): ResourceType
    {
        // Если company_id не указан, создаем компанию
        if (!isset($data['company_id']) && isset($data['timetable_id'])) {
            // Получаем company_id из расписания
            $timetable = \App\Models\Timetable::find($data['timetable_id']);
            if ($timetable) {
                $data['company_id'] = $timetable->company_id;
            }
        }

        return ResourceType::create($data);
    }
}
