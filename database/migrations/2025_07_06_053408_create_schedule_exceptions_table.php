<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->date('date');
            $table->enum('type', ['cancelled', 'moved', 'makeup', 'special']);
            $table->time('new_start_time')->nullable();
            $table->time('new_end_time')->nullable();
            $table->string('new_room')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['schedule_id', 'date'], 'unique_schedule_exception');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
    }
};
