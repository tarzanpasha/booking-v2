<?php

namespace App\Data;

class ScenarioOptionsData
{
    public static function getResourceOptionsForScenario(int $scenarioId): array
    {
        $options = [
            1 => ['specialization' => 'Парикмахер', 'experience' => '5 лет'],
            2 => ['location' => 'Этаж 3', 'capacity' => 8, 'equipment' => ['projector', 'whiteboard']],
            3 => ['location' => 'Зал А', 'trainer' => 'Иван Петров', 'type' => 'Йога'],
            4 => ['name' => '3D принтер', 'model' => 'Ultimaker S5', 'value' => '250000 руб'],
            5 => ['room_number' => '404', 'type' => 'Стандарт', 'beds' => 2],
            6 => ['priority' => 'high', 'emergency_contact' => '+7-XXX-XXX-XX-XX'],
            7 => ['specialization' => 'Косметолог', 'services' => ['маникюр', 'педикюр']],
            8 => ['location' => 'Бизнес-центр "Сити"', 'floor' => '15', 'capacity' => 50]
        ];

        return $options[$scenarioId] ?? ['scenario_id' => $scenarioId, 'demo' => true];
    }

    public static function getScenarioDescription(int $scenarioId): string
    {
        $descriptions = [
            1 => "Парикмахерская услуга с фиксированными слотами и автоматическим подтверждением",
            2 => "Переговорная комната с динамическими слотами и ручным подтверждением",
            3 => "Групповая тренировка с фиксированными слотами и ограничением участников",
            4 => "Дорогое оборудование с динамическими слотами и строгими ограничениями",
            5 => "Гостиничный номер с переходящими бронями на несколько дней",
            6 => "Экстренные случаи с приоритетом администратора",
            7 => "Салон красоты со статическим расписанием и учетом праздничных дней",
            8 => "Бизнес-центр со сложным расписанием и множественными перерывами"
        ];

        return $descriptions[$scenarioId] ?? "Демонстрационный сценарий {$scenarioId}";
    }
}
