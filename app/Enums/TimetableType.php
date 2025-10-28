<?php

namespace App\Enums;

enum TimetableType: string
{
    case STATIC = 'static';
    case DINAMIC = 'dinamic';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values());
    }

    public function label(): string
    {
        return match($this) {
            self::STATIC => 'Static Schedule',
            self::DINAMIC => 'dinamic Schedule',
        };
    }

    public function isStatic(): bool
    {
        return $this === self::STATIC;
    }

    public function isDinamic(): bool
    {
        return $this === self::DINAMIC;
    }
}
