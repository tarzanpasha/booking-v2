<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED_BY_CLIENT = 'cancelled_by_client';
    case CANCELLED_BY_ADMIN = 'cancelled_by_admin';
    case REJECTED = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Ожидает подтверждения',
            self::CONFIRMED => 'Подтверждена',
            self::CANCELLED_BY_CLIENT => 'Отменена клиентом',
            self::CANCELLED_BY_ADMIN => 'Отменена администратором',
            self::REJECTED => 'Отклонена',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED]);
    }

    public function isCancelled(): bool
    {
        return in_array($this, [self::CANCELLED_BY_CLIENT, self::CANCELLED_BY_ADMIN, self::REJECTED]);
    }
}
