<?php

namespace App\Actions;

use App\Models\Timetable;
use Illuminate\Support\Facades\Storage;

class StoreTimetableAction
{
    public function execute(array $data): Timetable
    {
        // Сохраняем файл расписания
        $filename = 'timetable_' . time() . '.json';
        Storage::put('imports/' . $filename, json_encode($data['schedule']));

        $timetable = Timetable::create($data);

        return $timetable;
    }
}
