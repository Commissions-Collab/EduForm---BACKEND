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
        static $gradeNumber = 1;
        
        return [
            'name' => "Grade {$gradeNumber}",
            'code' => "G{$gradeNumber}",
            'sort_order' => $gradeNumber++,
        ];
    }
}
