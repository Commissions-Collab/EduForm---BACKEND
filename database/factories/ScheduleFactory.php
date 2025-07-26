<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        $startTime = fake()->time('H:i:s', '16:00:00');
        $endTime = date('H:i:s', strtotime($startTime) + 3600); // 1 hour later
        
        return [
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'section_id' => Section::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'day_of_week' => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room' => fake()->optional(0.8)->bothify('Room ###'),
            'is_active' => fake()->boolean(95),
        ];
    }
}
