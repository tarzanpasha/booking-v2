<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Booking\BookingService;
use App\Services\Booking\SlotGenerationService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SlotGenerationService::class);
        $this->app->singleton(BookingService::class);
    }

    public function boot(): void
    {
        //
    }
}
