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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('year_level_id')->constrained('year_levels')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->string('name'); // e.g., "Section A", "Section B"
            $table->integer('capacity')->default(40);
            $table->timestamps();
            $table->softDeletes(); 

            // One section name per year level per academic year
            $table->unique(['year_level_id', 'academic_year_id', 'name'], 'unique_section_per_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
