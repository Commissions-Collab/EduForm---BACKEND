<?php

namespace Database\Factories;

use App\Models\StudentBmi;
use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\Quarter;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentBmiFactory extends Factory
{
    protected $model = StudentBmi::class;

    public function definition(): array
    {
        $height = $this->faker->randomFloat(1, 140.0, 185.0); // Height in cm
        $weight = $this->faker->randomFloat(1, 35.0, 90.0);   // Weight in kg
        
        // Calculate BMI
        $bmi = $weight / (($height / 100) * ($height / 100));
        
        // Determine BMI category
        $bmiCategory = $this->getBmiCategory($bmi);

        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'quarter_id' => Quarter::factory(),
            'recorded_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'height_cm' => $height,
            'weight_kg' => $weight,
            'bmi' => round($bmi, 2),
            'bmi_category' => $bmiCategory,
            'remarks' => $this->generateRemarks($bmiCategory),
        ];
    }

    private function getBmiCategory(float $bmi): string
    {
        if ($bmi < 18.5) {
            return 'Underweight';
        } elseif ($bmi >= 18.5 && $bmi < 25.0) {
            return 'Normal weight';
        } elseif ($bmi >= 25.0 && $bmi < 30.0) {
            return 'Overweight';
        } else {
            return 'Obese';
        }
    }

    private function generateRemarks(string $category): ?string
    {
        $remarks = [
            'Underweight' => [
                'Encourage increased caloric intake with nutritious foods',
                'Recommend consultation with school nutritionist',
                'Monitor eating habits and appetite',
            ],
            'Normal weight' => [
                'Maintain current healthy lifestyle',
                'Continue balanced diet and regular exercise',
                'Keep up the good work!',
            ],
            'Overweight' => [
                'Encourage increased physical activity',
                'Recommend portion control and healthy food choices',
                'Consider nutritional counseling',
            ],
            'Obese' => [
                'Recommend immediate consultation with healthcare provider',
                'Encourage supervised physical activity program',
                'Family involvement in healthy lifestyle changes recommended',
            ],
        ];

        return $this->faker->optional(0.7)->randomElement($remarks[$category] ?? [null]);
    }

    public function underweight(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->randomFloat(1, 150.0, 170.0);
            $weight = $this->faker->randomFloat(1, 35.0, 47.0); // Low weight for height
            $bmi = $weight / (($height / 100) * ($height / 100));
            
            return [
                'height_cm' => $height,
                'weight_kg' => $weight,
                'bmi' => round($bmi, 2),
                'bmi_category' => 'Underweight',
                'remarks' => 'Student appears underweight. Recommend nutritional assessment.',
            ];
        });
    }

    public function normalWeight(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->randomFloat(1, 150.0, 170.0);
            $weight = $this->faker->randomFloat(1, 50.0, 65.0); // Normal weight for height
            $bmi = $weight / (($height / 100) * ($height / 100));
            
            return [
                'height_cm' => $height,
                'weight_kg' => $weight,
                'bmi' => round($bmi, 2),
                'bmi_category' => 'Normal weight',
                'remarks' => 'Student maintains healthy weight. Continue current lifestyle.',
            ];
        });
    }

    public function overweight(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->randomFloat(1, 150.0, 170.0);
            $weight = $this->faker->randomFloat(1, 68.0, 85.0); // Higher weight for height
            $bmi = $weight / (($height / 100) * ($height / 100));
            
            return [
                'height_cm' => $height,
                'weight_kg' => $weight,
                'bmi' => round($bmi, 2),
                'bmi_category' => 'Overweight',
                'remarks' => 'Student is overweight. Recommend increased physical activity and dietary counseling.',
            ];
        });
    }
}