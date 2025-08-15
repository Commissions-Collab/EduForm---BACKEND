<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_current' => true,
        ]);
    }

    public function academicYear2023_2024(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => '2023-2024',
            'start_date' => '2023-06-01',
            'end_date' => '2024-05-31',
            'is_current' => false,
        ]);
    }

    public function academicYear2024_2025(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => '2024-2025',
            'start_date' => '2024-06-01',
            'end_date' => '2025-05-31',
            'is_current' => false,
        ]);
    }

    public function academicYear2025_2026(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => '2025-2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'is_current' => true,
        ]);
    }
}
