<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
