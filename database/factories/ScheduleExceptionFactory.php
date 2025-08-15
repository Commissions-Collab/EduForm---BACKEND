<?php

namespace Database\Factories;

use App\Models\ScheduleException;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleExceptionFactory extends Factory
{
    protected $model = ScheduleException::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['cancelled', 'moved', 'makeup', 'special']);
        
        return [
            'schedule_id' => Schedule::factory(),
            'date' => $this->faker->dateTimeBetween('2023-06-01', '2026-05-31')->format('Y-m-d'),
            'type' => $type,
            'new_start_time' => $type === 'moved' ? $this->faker->time() : null,
            'new_end_time' => $type === 'moved' ? $this->faker->time() : null,
            'new_room' => $type === 'moved' ? 'Room ' . $this->faker->numberBetween(101, 210) : null,
            'reason' => $this->faker->sentence(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cancelled',
            'new_start_time' => null,
            'new_end_time' => null,
            'new_room' => null,
            'reason' => $this->faker->randomElement([
                'Teacher sick leave',
                'School event',
                'Holiday',
                'Emergency'
            ]),
        ]);
    }

    public function moved(): static
    {
        $startHour = $this->faker->numberBetween(7, 15);
        return $this->state(fn (array $attributes) => [
            'type' => 'moved',
            'new_start_time' => sprintf('%02d:00:00', $startHour),
            'new_end_time' => sprintf('%02d:00:00', $startHour + 1),
            'new_room' => 'Room ' . $this->faker->numberBetween(101, 210),
        ]);
    }
}