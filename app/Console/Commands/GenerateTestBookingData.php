<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\GenerateTestDataAction;

class GenerateTestBookingData extends Command
{
    protected $signature = 'booking:generate-test-data {--company-id=}';
    protected $description = 'Generate test data for booking system using Artisan commands';

    public function handle(GenerateTestDataAction $generateTestDataAction): void
    {
        $companyId = $this->option('company-id') ?? 1;

        $this->info("🏢 Создание тестовых данных для компании {$companyId}...");
        $this->info("📋 Используются реальные Artisan команды для генерации расписаний");

        $result = $generateTestDataAction->execute($companyId);

        $this->info("🎉 Тестовые данные успешно созданы!");

        // Выводим детальную статистику
        $this->info("\n📊 Детальная статистика:");
        $this->info("   - Компания: {$result['company']->name} (ID: {$result['company']->id})");

        $this->info("   - Расписания:");
        $this->info("     • Статическое: {$result['timetable_info']['static']['working_days']} рабочих дней, {$result['timetable_info']['static']['total_breaks']} перерывов");
        $this->info("     • Динамическое: {$result['timetable_info']['dynamic']['working_days']} рабочих дней, {$result['timetable_info']['dynamic']['total_breaks']} перерывов");

        $this->info("   - Типы ресурсов: " . count($result['resource_types']));
        foreach ($result['resource_types'] as $type => $resourceType) {
            $this->info("     • {$resourceType->name}: {$resourceType->description}");
        }

        $resourcesByType = [];
        foreach ($result['resources'] as $resource) {
            $typeName = $resource->resourceType->name;
            $resourcesByType[$typeName] = ($resourcesByType[$typeName] ?? 0) + 1;
        }

        $this->info("   - Ресурсы: " . count($result['resources']));
        foreach ($resourcesByType as $typeName => $count) {
            $this->info("     • {$typeName}: {$count} шт.");
        }

        $this->info("   - Бронирования: " . count($result['bookings']));

        $bookingsByStatus = [];
        foreach ($result['bookings'] as $booking) {
            $bookingsByStatus[$booking->status] = ($bookingsByStatus[$booking->status] ?? 0) + 1;
        }

        foreach ($bookingsByStatus as $status => $count) {
            $this->info("     • {$status}: {$count} шт.");
        }

        $this->info("\n🚀 Система готова к тестированию!");
        $this->info("💡 Используйте: php artisan serve");
        $this->info("📚 API документация в Postman коллекции: storage/app/exports/booking_system_postman_collection.json");
    }
}
