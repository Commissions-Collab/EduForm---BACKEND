<?php

namespace Database\Factories;

use App\Models\HealthProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HealthProfileFactory extends Factory
{
    protected $model = HealthProfile::class;

    public function definition(): array
    {
        $ageGroup = $this->faker->numberBetween(12, 18); // typical high school age
        
        // Height ranges by age (in cm)
        $heightRanges = [
            12 => [140, 160],
            13 => [145, 165],
            14 => [150, 170],
            15 => [155, 175],
            16 => [160, 180],
            17 => [160, 185],
            18 => [160, 185]
        ];
        
        $height = $this->faker->randomFloat(2, $heightRanges[$ageGroup][0], $heightRanges[$ageGroup][1]);
        
        // Weight based on height (rough estimation)
        $minWeight = ($height - 100) * 0.8;
        $maxWeight = ($height - 100) * 1.3;
        $weight = $this->faker->randomFloat(2, max($minWeight, 35), min($maxWeight, 100));

        return [
            'student_id' => Student::factory(),
            'height' => $height,
            'weight' => $weight,
            'notes' => $this->faker->optional()->paragraph(),
            'updated_by' => User::factory()->teacher(),
        ];
    }

    public function underweight(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->randomFloat(2, 150, 170);
            return [
                'height' => $height,
                'weight' => $this->faker->randomFloat(2, 35, ($height - 100) * 0.7),
                'notes' => 'Student appears underweight. Recommend nutritional counseling.',
            ];
        });
    }

    public function overweight(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->randomFloat(2, 150, 170);
            return [
                'height' => $height,
                'weight' => $this->faker->randomFloat(2, ($height - 100) * 1.4, ($height - 100) * 1.8),
                'notes' => 'Student appears overweight. Recommend physical activity and diet counseling.',
            ];
        });
    }
}