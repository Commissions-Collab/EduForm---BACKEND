<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Sync existing schedules to teacher_subjects table
     */
    public function up(): void
    {
        // Get all unique combinations from schedules
        $schedules = DB::table('schedules')
            ->select('teacher_id', 'subject_id', 'section_id', 'academic_year_id', 'quarter_id')
            ->distinct()
            ->get();

        foreach ($schedules as $schedule) {
            // Check if teacher_subject already exists
            $exists = DB::table('teacher_subjects')
                ->where('teacher_id', $schedule->teacher_id)
                ->where('subject_id', $schedule->subject_id)
                ->where('section_id', $schedule->section_id)
                ->where('academic_year_id', $schedule->academic_year_id)
                ->where('quarter_id', $schedule->quarter_id)
                ->exists();

            // Create if doesn't exist
            if (!$exists) {
                DB::table('teacher_subjects')->insert([
                    'teacher_id' => $schedule->teacher_id,
                    'subject_id' => $schedule->subject_id,
                    'section_id' => $schedule->section_id,
                    'academic_year_id' => $schedule->academic_year_id,
                    'quarter_id' => $schedule->quarter_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback - we don't want to delete synced records
    }
};
