<?php

namespace App\Enums;

enum SlotStrategy: string
{
    case FIXED = 'fixed';
    case DYNAMIC = 'dynamic';

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
            self::FIXED => 'fixed',
            self::DYNAMIC => 'dynamic',
        };
    }
}
