<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    public $incrementing = false; // Отключаем автоинкремент
    protected $keyType = 'integer'; // Тип ключа

    protected $fillable = ['id', 'name', 'description'];

    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }

    public function resourceTypes(): HasMany
    {
        return $this->hasMany(ResourceType::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
