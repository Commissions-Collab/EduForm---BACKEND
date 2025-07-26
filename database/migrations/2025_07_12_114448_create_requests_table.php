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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_to')->constrained('users')->onDelete('cascade');
            $table->string('request_type');
            $table->string('email');
            $table->string('password');
            $table->enum('role', ['student']);
            $table->string('LRN', 12);
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('birthday');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('parents_fullname');
            $table->enum('relationship_to_student', ['Father', 'Mother', 'Guardian']);
            $table->string('parents_number', 15);
            $table->string('parents_email')->nullable();
            $table->string('image');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
