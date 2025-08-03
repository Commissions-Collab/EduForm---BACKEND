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
        Schema::table('book_inventories', function (Blueprint $table) {
            // Drop old borrowing-specific columns
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
            $table->dropColumn('status');
            $table->dropColumn('issued_date');
            $table->dropColumn('returned_date');

            // Add new columns
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->integer('total_copies')->default(0);
            $table->integer('available')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_inventories', function (Blueprint $table) {
            // Drop the new foreign keys and columns
            $table->dropForeign(['teacher_id']);
            $table->dropForeign(['subject_id']);
            $table->dropColumn(['teacher_id', 'subject_id', 'total_copies', 'available']);

            // Re-add previously removed columns
            $table->enum('status', ['issued', 'returned'])->default('issued');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
        });
    }
};
