<?php

namespace App\Data;

class DynamicScheduleTemplates
{
    public static function getScheduleTypes(): array
    {
        return [
            '8h_normal' => ['start' => '09:00', 'end' => '17:00'],
            '8h_early' => ['start' => '08:00', 'end' => '16:00'],
            '8h_late' => ['start' => '10:00', 'end' => '18:00'],
            '10h' => ['start' => '08:00', 'end' => '18:00'],
            '12h' => ['start' => '08:00', 'end' => '20:00'],
            '24h' => ['start' => '08:00', 'end' => '08:00'], // Следующего дня
            '6h_short' => ['start' => '10:00', 'end' => '16:00'],
        ];
    }

    public static function getBreakConfigurations(): array
    {
        return [
            '8h_normal' => ['break_count' => [1, 2], 'min_duration' => 30, 'max_duration' => 60],
            '8h_early' => ['break_count' => [1, 2], 'min_duration' => 30, 'max_duration' => 60],
            '8h_late' => ['break_count' => [1, 2], 'min_duration' => 30, 'max_duration' => 60],
            '10h' => ['break_count' => [2, 3], 'min_duration' => 15, 'max_duration' => 45],
            '12h' => ['break_count' => [3, 4], 'min_duration' => 15, 'max_duration' => 60],
            '24h' => ['break_count' => [4, 6], 'min_duration' => 15, 'max_duration' => 60],
            '6h_short' => ['break_count' => [0, 1], 'min_duration' => 15, 'max_duration' => 30],
        ];
    }
}
