<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Timetable;
use App\Models\ResourceType;
use App\Models\Resource;
use Carbon\Carbon;

trait BookingFixtures
{
    protected function freezeNow(string $datetime = '2026-02-03 08:00:00'): Carbon
    {
        $now = Carbon::parse($datetime);
        Carbon::setTestNow($now);
        return $now;
    }

    protected function createCompany(int $id = 1, ?string $name = 'Test Company'): Company
    {
        return Company::create([
            'id' => $id,
            'name' => $name,
            'description' => 'Test',
        ]);
    }

    /**
     * Делает расписание на ВСЕ дни недели, чтобы тесты не зависели от конкретной даты/дня.
     */
    protected function createStaticTimetable(Company $company, array $overridesSchedule = []): Timetable
    {
        $defaultDay = [
            'working_hours' => [
                'start' => '09:00',
                'end' => '18:00',
            ],
            'breaks' => [],
        ];

        $schedule = [
            'days' => [
                'monday' => $defaultDay,
                'tuesday' => $defaultDay,
                'wednesday' => $defaultDay,
                'thursday' => $defaultDay,
                'friday' => $defaultDay,
                'saturday' => $defaultDay,
                'sunday' => $defaultDay,
            ],
            'holidays' => [],
        ];



        // Простой merge
        foreach ($overridesSchedule as $k => $v) {
            $schedule[$k] = $v;
        }

        return Timetable::create([
            'company_id' => $company->id,
            'type' => 'static',
            'schedule' => $schedule,
        ]);
    }


    /**
     * Dynamic timetable адресуется по ключу "m-d" (например "02-03").
     * schedule = ['dates' => ['02-03' => ['working_hours' => ..., 'breaks' => ...]]]
     */
    protected function createDynamicTimetable(Company $company, array $datesMap): Timetable
    {
        return Timetable::create([
            'company_id' => $company->id,
            'type' => 'dynamic',
            'schedule' => [
                'dates' => $datesMap,
            ],
        ]);
    }

    protected function createResourceType(
        Company $company,
        ?Timetable $timetable,
        string $type,
        array $resourceConfig = []
    ): ResourceType {
        return ResourceType::create([
            'company_id' => $company->id,
            'timetable_id' => $timetable?->id,
            'type' => $type,
            'name' => 'Type ' . $type,
            'description' => 'Test',
            'options' => [],
            'resource_config' => $resourceConfig,
        ]);
    }

    protected function createResource(
        Company $company,
        ResourceType $resourceType,
        ?Timetable $timetable,
        array $resourceConfig = []
    ): Resource {
        return Resource::create([
            'company_id' => $company->id,
            'resource_type_id' => $resourceType->id,
            'timetable_id' => $timetable?->id,
            'options' => [],
            'payload' => [],
            'resource_config' => $resourceConfig,
        ]);
    }

    protected function dt(string $datetime): Carbon
    {
        return Carbon::parse($datetime);
    }
}
