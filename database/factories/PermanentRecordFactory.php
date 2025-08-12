<?php

namespace Database\Factories;

use App\Models\PermanentRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermanentRecordFactory extends Factory
{
    protected $model = PermanentRecord::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school_year' => $this->faker->randomElement(['2023-2024', '2024-2025', '2025-2026']),
            'final_average' => $this->faker->randomFloat(2, 75, 98),
            'remarks' => $this->faker->optional()->randomElement([
                'Promoted',
                'Retained',
                'Transferred',
                'Completed',
                'With Honors'
            ]),
            'validated_by' => User::factory()->teacher(),
        ];
    }

    public function withHonors(): static
    {
        return $this->state(fn (array $attributes) => [
            'final_average' => $this->faker->randomFloat(2, 90, 98),
            'remarks' => 'With Honors',
        ]);
    }

    public function promoted(): static
    {
        return $this->state(fn (array $attributes) => [
            'final_average' => $this->faker->randomFloat(2, 75, 89),
            'remarks' => 'Promoted',
        ]);
    }
}