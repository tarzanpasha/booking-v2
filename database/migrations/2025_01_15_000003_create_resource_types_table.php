<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('timetable_id')->nullable();
            $table->string('type', 63);
            $table->string('name', 127);
            $table->string('description', 255)->nullable();
            $table->json('options')->nullable();
            $table->json('resource_config')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'type']);

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('timetable_id')->references('id')->on('timetables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_types');
    }
};
