<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица бронирований
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('timetable_id')->nullable()->constrained('timetables')->nullOnDelete();
            $table->boolean('is_group_booking')->default(false);
            $table->dateTime('start');
            $table->dateTime('end');
            $table->string('status')->default('pending');
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['resource_id', 'start', 'end']);
            $table->index(['company_id', 'start']);
        });

        // Таблица бронирующих (клиентов/администраторов)
        Schema::create('bookers', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable();
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['external_id', 'type']);
        });

        // Связующая таблица между бронированиями и бронирующими
        Schema::create('booking_booker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booker_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'booker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_booker');
        Schema::dropIfExists('bookers');
        Schema::dropIfExists('bookings');
    }
};
