<?php

namespace App\Providers;

use App\Services\Booking\BookingService2;
use App\Services\Booking\SlotGenerationService2;
use Illuminate\Support\ServiceProvider;
use App\Services\Booking\BookingService;
use App\Services\Booking\SlotGenerationService;
use App\Services\ArtisanCommandService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SlotGenerationService::class);
        $this->app->singleton(BookingService::class);
        $this->app->singleton(ArtisanCommandService::class);
        $this->app->singleton(SlotGenerationService2::class);
        $this->app->singleton(BookingService2::class);
    }

    public function boot(): void
    {
        //
    }
}
