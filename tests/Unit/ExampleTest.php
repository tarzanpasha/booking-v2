<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ExampleTest extends TestCase
{
    public function test_env_testing_loaded_and_db_connection_works(): void
    {
        // Проверяем, что поднялось Laravel окружение для тестов
        $this->assertSame('testing', config('app.env'));

        // Проверяем, что используем нужную БД (из .env.testing)
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('booking_test', config('database.connections.mysql.database'));

        // Проверяем соединение
        $row = DB::selectOne('SELECT 1 AS ok');
        $this->assertSame(1, (int) $row->ok);
    }
}
