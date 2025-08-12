<?php

namespace Database\Factories;

use App\Models\BookInventory;
use App\Models\Teacher;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookInventoryFactory extends Factory
{
    protected $model = BookInventory::class;

    public function definition(): array
    {
        $subjects = [
            'Mathematics' => [
                'Mathematics for Grade 7',
                'Algebra and Geometry',
                'Statistics and Probability',
                'Advanced Mathematics'
            ],
            'English' => [
                'English Grammar and Composition',
                'Literature and Reading',
                'Speech and Communication',
                'Creative Writing'
            ],
            'Science' => [
                'General Science',
                'Biology Fundamentals',
                'Chemistry Basics',
                'Physics Principles'
            ],
            'Filipino' => [
                'Wikang Filipino',
                'Panitikang Filipino',
                'Gramatika at Komposisyon',
                'Kultura at Kasaysayan'
            ]
        ];

        $subjectName = $this->faker->randomElement(array_keys($subjects));
        $title = $this->faker->randomElement($subjects[$subjectName]);
        $totalCopies = $this->faker->numberBetween(10, 100);

        return [
            'title' => $title,
            'teacher_id' => Teacher::factory(),
            'subject_id' => Subject::factory(),
            'total_copies' => $totalCopies,
            'available' => $this->faker->numberBetween(0, $totalCopies),
        ];
    }

    public function fullyStocked(): static
    {
        return $this->state(function (array $attributes) {
            $totalCopies = $this->faker->numberBetween(50, 100);
            return [
                'total_copies' => $totalCopies,
                'available' => $totalCopies,
            ];
        });
    }

    public function outOfStock(): static
    {
        return $this->state(function (array $attributes) {
            $totalCopies = $this->faker->numberBetween(10, 50);
            return [
                'total_copies' => $totalCopies,
                'available' => 0,
            ];
        });
    }
}