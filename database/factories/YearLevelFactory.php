<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\YearLevel>
 */
class YearLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Grade 7',
                'Grade 8',
                'Grade 9',
                'Grade 10',
                'Grade 11',
                'Grade 12'
            ]),
            'admin_id' => User::where('role', 'super_admin')->inRandomOrder()->first()?->id
                ?? User::factory()->create(['role' => 'super_admin'])->id,
        ];
    }
}
