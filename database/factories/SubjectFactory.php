<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    private static $subjects = [
        ['name' => 'Mathematics', 'code' => 'MATH', 'units' => 1],
        ['name' => 'English', 'code' => 'ENG', 'units' => 1],
        ['name' => 'Filipino', 'code' => 'FIL', 'units' => 1],
        ['name' => 'Science', 'code' => 'SCI', 'units' => 1],
        ['name' => 'Social Studies', 'code' => 'SS', 'units' => 1],
        ['name' => 'Physical Education', 'code' => 'PE', 'units' => 1],
        ['name' => 'Music', 'code' => 'MUS', 'units' => 1],
        ['name' => 'Arts', 'code' => 'ARTS', 'units' => 1],
        ['name' => 'Values Education', 'code' => 'VE', 'units' => 1],
        ['name' => 'Health', 'code' => 'HEALTH', 'units' => 1],
    ];

    private static $index = 0;

    public function definition(): array
    {
        if (self::$index >= count(self::$subjects)) {
            self::$index = 0;
        }
        
        $subject = self::$subjects[self::$index++];
        
        return [
            'name' => $subject['name'],
            'code' => $subject['code'] . fake()->numberBetween(100, 199),
            'description' => fake()->optional(0.8)->sentence(10),
            'units' => $subject['units'],
            'is_active' => fake()->boolean(95),
        ];
    }
}
