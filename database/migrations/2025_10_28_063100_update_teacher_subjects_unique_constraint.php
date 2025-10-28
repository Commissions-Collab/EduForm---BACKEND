<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update unique constraint to allow same subject in different sections/quarters
     * NOTE: The old constraint must be dropped manually via SQL before running this
     */
    public function up(): void
    {
        // Check if new constraint already exists
        $newIndexExists = DB::select("SHOW INDEX FROM teacher_subjects WHERE Key_name = 'unique_teacher_subject_section_quarter'");
        
        // Only add new constraint if it doesn't exist
        if (empty($newIndexExists)) {
            Schema::table('teacher_subjects', function (Blueprint $table) {
                $table->unique(
                    ['teacher_id', 'subject_id', 'section_id', 'academic_year_id', 'quarter_id'],
                    'unique_teacher_subject_section_quarter'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_subjects', function (Blueprint $table) {
            // Drop new constraint
            $table->dropUnique('unique_teacher_subject_section_quarter');
        });
        
        // Restore old constraint using raw SQL
        DB::statement('ALTER TABLE teacher_subjects ADD UNIQUE KEY unique_teacher_subject_year (teacher_id, subject_id, academic_year_id)');
    }
};
