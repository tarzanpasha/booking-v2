<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('bookables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booker_id')->constrained()->cascadeOnDelete();
            $table->morphs('bookable');
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookables');
        Schema::dropIfExists('bookers');
    }
};
