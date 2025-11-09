<?php

namespace App\Data;

class WorkingHoursTemplates
{
    public static function getWorkingDaysPatterns(): array
    {
        return [
            ['monday', 'wednesday', 'friday'],    // Пн, Ср, Пт
            ['tuesday', 'thursday', 'saturday'],  // Вт, Чт, Сб
        ];
    }

    public static function getWorkingHoursTemplates(): array
    {
        return [
            [
                'regular' => ['start' => '08:00', 'end' => '17:00'],
                'early' => ['start' => '08:00', 'end' => '16:00'] // На час раньше
            ],
            [
                'regular' => ['start' => '09:00', 'end' => '18:00'],
                'early' => ['start' => '09:00', 'end' => '17:00'] // На час раньше
            ],
            [
                'regular' => ['start' => '10:00', 'end' => '19:00'],
                'early' => ['start' => '10:00', 'end' => '18:00'] // На час раньше
            ]
        ];
    }

    public static function getRussianHolidays(): array
    {
        return [
            '01-01', '01-02', '01-03', '01-04', '01-05', '01-06', '01-08', // Новогодние каникулы
            '01-07', // Рождество
            '02-23', // День защитника Отечества
            '03-08', // Международный женский день
            '05-01', // Праздник Весны и Труда
            '05-09', // День Победы
            '06-12', // День России
            '11-04', // День народного единства
        ];
    }
}
