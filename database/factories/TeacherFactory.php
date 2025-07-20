<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        // Ensure required related models exist
        $user = User::inRandomOrder()->first() ?? User::factory()->create();
        $section = Section::inRandomOrder()->first() ?? Section::factory()->create();
        $subject = Subject::inRandomOrder()->first() ?? Subject::factory()->create();

        return [
            'user_id' => $user->id,
            'name' => $this->faker->name,
            'is_advisor_id' => $section->id,
            'subject_id' => $subject->id,
        ];
    }
}
