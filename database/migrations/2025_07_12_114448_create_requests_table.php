<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('student');
            $table->string('LRN')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('birthday');
            $table->string('gender');
            $table->string('parents_fullname')->nullable();
            $table->string('relationship_to_student')->nullable();
            $table->string('parents_number')->nullable();
            $table->string('parents_email')->nullable();
            $table->string('image')->nullable(); // Make this nullable
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('request_to')->nullable();
            $table->string('request_type')->default('student_signup');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
