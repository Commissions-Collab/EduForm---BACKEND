<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\YearLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        // Ensure YearLevel and Admin exist
        $yearLevel = YearLevel::inRandomOrder()->first() ?? YearLevel::factory()->create();
        

        return [
            'name' => $this->faker->unique()->randomLetter . '-' . $this->faker->randomElement(['1', '2', '3', '4']),
            'year_level_id' => $yearLevel->id,
        ];
    }
}
