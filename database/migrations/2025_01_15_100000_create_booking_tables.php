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

        // Таблица для morph-связи (заменяет старые bookers и booking_booker)
        Schema::create('bookables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('reason', 255)->nullable();
            $table->morphs('bookable'); // bookable_id, bookable_type
            $table->timestamps();

            $table->unique(['booking_id', 'bookable_id', 'bookable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookables');
        Schema::dropIfExists('bookings');
    }
};
