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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->foreignId('quarter_id')->constrained('quarters')->onDelete('cascade');

            // Attendance Details
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->time('time_in')->nullable(); // For late arrivals
            $table->time('time_out')->nullable(); // For early departures
            $table->text('remarks')->nullable();

            // Who recorded the attendance
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('recorded_at');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['attendance_date', 'academic_year_id'], 'idx_attendance_date_academic');
            $table->index(['student_id', 'quarter_id'], 'idx_attendance_student_quarter');
            // Ensure one attendance record per student per class per date
            // $table->unique(['student_id', 'schedule_id', 'attendance_date'], 'unique_student_class_attendance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
