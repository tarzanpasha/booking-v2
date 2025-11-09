<?php

namespace App\Data;

class ScenarioResourceConfigData
{
    public static function getResourceConfigForScenario(int $scenarioId): array
    {
        $configs = [
            1 => [
                'require_confirmation' => false,
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'fixed',
                'min_advance_time' => 60,
                'cancellation_time' => 120,
                'reschedule_time' => 240,
                'reminder_time' => 1440
            ],
            2 => [
                'require_confirmation' => true,
                'slot_duration_minutes' => 30,
                'slot_strategy' => 'dinamic',
                'min_advance_time' => 1440,
                'cancellation_time' => 720,
                'reschedule_time' => 1440
            ],
            3 => [
                'require_confirmation' => false,
                'slot_duration_minutes' => 90,
                'slot_strategy' => 'fixed',
                'max_participants' => 10,
                'min_advance_time' => 60,
                'cancellation_time' => 180,
                'reschedule_time' => 360
            ],
            4 => [
                'require_confirmation' => true,
                'slot_duration_minutes' => 120,
                'slot_strategy' => 'dinamic',
                'min_advance_time' => 2880, // 48 часов
                'cancellation_time' => 0,    // Отмена невозможна для клиента
                'reschedule_time' => 0,      // Перенос невозможен для клиента
                'reminder_time' => 1440
            ],
            5 => [
                'require_confirmation' => false,
                'slot_duration_minutes' => 1440,
                'slot_strategy' => 'fixed',
                'min_advance_time' => 0,
                'cancellation_time' => 10080,
                'reschedule_time' => 10080
            ],
            6 => [
                'require_confirmation' => true,
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'dinamic',
                'min_advance_time' => 0,
                'cancellation_time' => 0,
                'reschedule_time' => 0
            ],
            7 => [
                'require_confirmation' => false,
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'fixed',
                'min_advance_time' => 120,
                'cancellation_time' => 180,
                'reschedule_time' => 360,
                'reminder_time' => 1440
            ],
            8 => [
                'require_confirmation' => true,
                'slot_duration_minutes' => 60,
                'slot_strategy' => 'dinamic',
                'max_participants' => 20,
                'min_advance_time' => 1440,
                'cancellation_time' => 720,
                'reschedule_time' => 1440
            ]
        ];

        return $configs[$scenarioId] ?? $configs[1];
    }
}
