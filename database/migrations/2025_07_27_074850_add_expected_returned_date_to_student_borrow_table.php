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
        Schema::table('student_borrow_books', function (Blueprint $table) {
            $table->date('expected_return_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_borrow_books', function (Blueprint $table) {
            $table->dropColumn('expected_return_date');
        });
    }
};
