<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\BookingCreated::class => [
            \App\Listeners\BookingCreatedListener::class,
        ],
        \App\Events\BookingConfirmed::class => [
            \App\Listeners\BookingConfirmedListener::class,
        ],
        \App\Events\BookingCancelled::class => [
            \App\Listeners\BookingCancelledListener::class,
        ],
        \App\Events\BookingRescheduled::class => [
            \App\Listeners\BookingRescheduledListener::class,
        ],
        \App\Events\BookingReminder::class => [
            \App\Listeners\BookingReminderListener::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
