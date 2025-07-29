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
        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('subject_id')->constrained('sections')->onDelete('cascade');
            $table->foreignId('quarter_id')->nullable()->after('academic_year_id')->constrained('quarters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropForeign(['quarter_id']);
            $table->dropColumn(['section_id', 'quarter_id']);
        });
    }
};
