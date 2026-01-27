<?php

namespace App\Services\Logging;

use Illuminate\Support\Facades\Log;

class BookingLoggerService
{
    public static function info(string $message, array $context = []): void
    {
        Log::channel('booking')->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::channel('booking')->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        Log::channel('booking')->error($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        Log::channel('booking')->debug($message, $context);
    }
}
