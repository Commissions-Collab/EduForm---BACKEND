<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Schedule;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['present', 'absent', 'late', 'excused']);
        
        return [
            'student_id' => Student::factory(),
            'schedule_id' => Schedule::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'quarter_id' => Quarter::factory(),
            'attendance_date' => $this->faker->dateTimeBetween('2023-06-01', '2026-05-31')->format('Y-m-d'),
            'status' => $status,
            'time_in' => $status === 'late' ? $this->faker->time() : null,
            'time_out' => $this->faker->optional(0.1)->time(), // 10% chance of early departure
            'remarks' => $this->faker->optional()->sentence(),
            'recorded_by' => User::factory()->teacher(),
            'recorded_at' => $this->faker->dateTimeThisYear(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
            'time_in' => null,
            'time_out' => null,
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
            'time_in' => null,
            'time_out' => null,
            'remarks' => $this->faker->randomElement([
                'Sick',
                'Family emergency',
                'Unexcused absence',
                'Medical appointment'
            ]),
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'late',
            'time_in' => $this->faker->time('H:i:s', '09:30:00'),
            'remarks' => $this->faker->randomElement([
                'Traffic',
                'Overslept',
                'Transportation issue',
                'Family matter'
            ]),
        ]);
    }
}