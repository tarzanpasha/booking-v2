<?php

namespace App\Console\Commands;

use App\Models\Resource;
use App\Services\Booking\BookingService2;
use App\Services\Booking\SlotGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ActualTestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:actual-tests-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Команда, пытающаяся прогнать все функции из ТЗ';

    /**
     * Execute the console command.
     */
    public function handle(SlotGenerationService $service): void
    {
        $from = Carbon::parse('12.02.2026 08:00:00');
        $to = Carbon::parse('14.02.2026 11:00:00');
        $resource = Resource::find(4);
        $slots = $service->getAvailableSlotsForPeriod($resource, $from, $to);
        return;
    }
}
