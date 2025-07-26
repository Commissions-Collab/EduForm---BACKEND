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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->string('lrn', 12)->unique(); // Learner Reference Number
            $table->string('student_id')->unique(); // School student ID
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('birthday');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('address')->nullable();
            $table->string('phone')->nullable();

            // Parent/Guardian Information
            $table->string('parent_guardian_name');
            $table->string('relationship_to_student');
            $table->string('parent_guardian_phone');
            $table->string('parent_guardian_email')->nullable();

            // Student Photo
            $table->string('photo')->nullable();

            // Enrollment Information
            $table->date('enrollment_date');
            $table->enum('enrollment_status', ['enrolled', 'transferred', 'graduated', 'dropped'])->default('enrolled');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
