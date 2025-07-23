<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduleException>
 */
class ScheduleExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $newStartTime = fake()->optional(0.6)->time('H:i:s', '16:00:00');
        $newEndTime = $newStartTime ? date('H:i:s', strtotime($newStartTime) + 3600) : null;
        
        return [
            'schedule_id' => Schedule::factory(),
            'date' => fake()->dateTimeBetween('-3 months', '+3 months'),
            'type' => fake()->randomElement(['cancelled', 'moved', 'makeup', 'special']),
            'new_start_time' => $newStartTime,
            'new_end_time' => $newEndTime,
            'new_room' => fake()->optional(0.4)->bothify('Room ###'),
            'reason' => fake()->optional(0.8)->sentence(8),
        ];
    }
}
