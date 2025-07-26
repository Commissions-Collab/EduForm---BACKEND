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
        Schema::create('academic_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->date('date');
            $table->enum('type', ['regular', 'holiday', 'exam', 'no_class', 'special_event']);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_class_day')->default(true); // False for holidays/no class days
            $table->timestamps();

            $table->unique(['academic_year_id', 'date'], 'unique_calendar_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_calendars');
    }
};
