<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->numberBetween(2022, 2025);
        $endYear = $startYear + 1;
        
        return [
            'name' => "{$startYear}-{$endYear}",
            'start_date' => Carbon::create($startYear, 8, 15),
            'end_date' => Carbon::create($endYear, 5, 30),
            'is_current' => false,
        ];
    }

    public function current()
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
        ]);
    }
}
