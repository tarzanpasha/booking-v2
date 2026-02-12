<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Resource;
use Faker\Factory as Faker;
use App\Models\User;
use App\Services\Booking\BookingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class CheckScenarioWothGroupBooking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-scenario-woth-group-booking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private function getNewUser(): User
    {
        $faker = Faker::create('ru_RU');

        $user = new User;
        $user->name = $faker->name;
        $user->email = $faker->email;
        $user->password = bcrypt($faker->password);
        $user->save();
        return $user;
    }

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle(BookingService $service): void
    {
        $resource = Resource::find(4);

        //$bookingManyPeople = $service->createBooking($resource, '28.01.2026 14:00', '28.01.2026 15:00', $this->getNewUser(), true);

        /*$service->attachBooker($bookingManyPeople, $this->getNewUser());
        $service->attachBooker($bookingManyPeople, $this->getNewUser());
        $service->attachBooker($bookingManyPeople, $this->getNewUser());*/

        //$bookingSingle = $service->createBooking($resource, '28.01.2026 18:00', '28.01.2026 19:00', $this->getNewUser(), true);

        //$bookingReshedules = $service->rescheduleBooking($bookingManyPeople->id, '28.01.2026 17:30', '28.01.2026 18:30', 'admin');

        $slots = $service->getNextAvailableSlots($resource, null, 15, false );

    }
}
