<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Resource;

class CheckResourceConfigs extends Command
{
    protected $signature = 'check:resource-configs {scenario?}';
    protected $description = 'Проверить конфигурации ресурсов для сценариев';

    public function handle(): void
    {
        $scenarioId = $this->argument('scenario');

        if ($scenarioId) {
            $this->checkScenarioConfig($scenarioId);
        } else {
            for ($i = 1; $i <= 8; $i++) {
                $this->checkScenarioConfig($i);
            }
        }
    }

    private function checkScenarioConfig(int $scenarioId): void
    {
        $companyId = $scenarioId * 100;

        $resources = Resource::where('company_id', $companyId)->get();

        $this->info("\n🔧 Проверка конфигурации для сценария {$scenarioId}:");

        if ($resources->count() === 0) {
            $this->warn("   ❌ Ресурсы не найдены для компании ID: {$companyId}");
            return;
        }

        foreach ($resources as $resource) {
            $config = $resource->getResourceConfig();

            $this->line("   Ресурс ID: {$resource->id}");
            $this->line("   - min_advance_time: {$config->min_advance_time} мин");
            $this->line("   - cancellation_time: " . ($config->cancellation_time ?? 'null') . " мин");
            $this->line("   - reschedule_time: " . ($config->reschedule_time ?? 'null') . " мин");
            $this->line("   - require_confirmation: " . ($config->requiresConfirmation() ? 'да' : 'нет'));
            $this->line("   - slot_duration_minutes: {$config->slot_duration_minutes} мин");
            $this->line("   - slot_strategy: {$config->slot_strategy->value}");
            $this->line("   - max_participants: " . ($config->max_participants ?? 'null'));
            $this->line("");
        }
    }
}
