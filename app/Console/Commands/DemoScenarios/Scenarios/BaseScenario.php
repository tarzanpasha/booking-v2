<?php
// app/Console/Commands/DemoScenarios/Scenarios/BaseScenario.php

namespace App\Console\Commands\DemoScenarios\Scenarios;

use App\Services\Booking\BookingService;
use App\Console\Commands\DemoScenarios\ScenarioRunnerService;
use Illuminate\Console\Command;

abstract class BaseScenario
{
    protected Command $command;
    protected ScenarioRunnerService $runner;
    protected BookingService $bookingService;

    protected int $scenarioId;
    protected string $name;
    protected string $description;

    public function __construct(
        Command $command,
        ScenarioRunnerService $runner,
        BookingService $bookingService
    ) {
        $this->command = $command;
        $this->runner = $runner;
        $this->bookingService = $bookingService;
    }

    abstract public function run(array $setupData): void;

    abstract public function getDescription(): string;

    public function getName(): string
    {
        return $this->name;
    }

    public function getScenarioId(): int
    {
        return $this->scenarioId;
    }

    // Общие методы для всех сценариев
    protected function info(string $message): void
    {
        $this->command->info($message);
    }

    protected function error(string $message): void
    {
        $this->command->error($message);
    }

    protected function line(string $message): void
    {
        $this->command->line($message);
    }

    protected function warn(string $message): void
    {
        $this->command->warn($message);
    }
}
