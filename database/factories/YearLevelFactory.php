<?php

namespace Database\Factories;

use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class YearLevelFactory extends Factory
{
    protected $model = YearLevel::class;

    public function definition(): array
    {
        $grades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
        $grade = $this->faker->randomElement($grades);
        $code = 'G' . str_replace('Grade ', '', $grade);

        return [
            'name' => $grade,
            'code' => $code,
            'sort_order' => (int) str_replace('Grade ', '', $grade),
        ];
    }
}
