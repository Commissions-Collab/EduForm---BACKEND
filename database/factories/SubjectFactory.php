<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $subjects = [
            'Mathematics' => 'MATH',
            'English' => 'ENG',
            'Science' => 'SCI',
            'Filipino' => 'FIL',
            'Araling Panlipunan' => 'AP',
            'Technology and Livelihood Education' => 'TLE',
            'Music, Arts, Physical Education and Health' => 'MAPEH',
            'Values Education' => 'VE',
        ];

        $subject = $this->faker->randomElement(array_keys($subjects));
        $code = $subjects[$subject] . $this->faker->numberBetween(100, 999);

        return [
            'name' => $subject,
            'code' => $code,
            'description' => $this->faker->optional()->sentence(),
            'units' => $this->faker->numberBetween(1, 3),
            'is_active' => true,
        ];
    }
}
