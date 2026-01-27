<?php

namespace App\Actions;

use App\Models\Timetable;
use Illuminate\Support\Facades\Storage;

class UpdateTimetableAction
{
    public function execute(Timetable $timetable, array $data): Timetable
    {
        if (isset($data['schedule'])) {
            // Сохраняем обновленный файл расписания
            $filename = 'timetable_' . $timetable->id . '_' . time() . '.json';
            Storage::put('imports/' . $filename, json_encode($data['schedule']));
        }

        $timetable->update($data);

        return $timetable;
    }
}
