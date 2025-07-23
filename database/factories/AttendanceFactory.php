<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['present', 'present', 'present', 'present', 'absent', 'late', 'excused']);
        
        return [
            'student_id' => Student::factory(),
            'schedule_id' => Schedule::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'attendance_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'status' => $status,
            'time_in' => $status === 'late' ? fake()->time('H:i:s') : null,
            'time_out' => fake()->optional(0.1)->time('H:i:s'),
            'remarks' => fake()->optional(0.3)->sentence(5),
            'recorded_by' => User::factory()->teacher(),
            'recorded_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }
}
