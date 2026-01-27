<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Bookable extends Model
{
    /**
     * Таблица, связанная с моделью.
     */
    protected $table = 'bookables';

    /**
     * Атрибуты, которые можно массово назначать.
     */
    protected $fillable = [
        'booking_id',
        'reason',
        'status',
        'bookable_id',
        'bookable_type',
    ];

    /**
     * Получить связанное бронирование.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Получить связанную модель (морфическое отношение).
     */
    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }
}
