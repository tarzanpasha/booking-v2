<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('timetable_id')->nullable();
            $table->foreignId('resource_type_id')->constrained('resource_types')->cascadeOnDelete();
            $table->json('options')->nullable();
            $table->json('payload')->nullable();
            $table->json('resource_config')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('timetable_id')->references('id')->on('timetables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
