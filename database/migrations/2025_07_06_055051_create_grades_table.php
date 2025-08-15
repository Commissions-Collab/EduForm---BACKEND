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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('quarter_id')->constrained('quarters');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->decimal('grade', 5, 2);
            $table->foreignId('recorded_by')->constrained('users'); // referencing teacher
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id'], 'idx_grades_student_academic');
            $table->index(['subject_id', 'quarter_id'], 'idx_grades_subject_quarter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
