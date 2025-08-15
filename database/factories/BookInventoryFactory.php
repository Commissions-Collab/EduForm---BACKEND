<?php

namespace Database\Factories;

use App\Models\BookInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookInventoryFactory extends Factory
{
    protected $model = BookInventory::class;

    public function definition(): array
    {
        // A sample list of subjects and corresponding book titles
        $subjects = [
            'Mathematics' => ['Mathematics for Grade 7', 'Algebra and Geometry', 'Statistics and Probability', 'Advanced Mathematics', 'Pre-Calculus', 'Basic Calculus'],
            'English' => ['English Grammar and Composition', 'Literature and Reading', 'Speech and Communication', 'Creative Writing', 'Reading and Writing Skills'],
            'Science' => ['General Science', 'Biology Fundamentals', 'Chemistry Basics', 'Physics Principles', 'Earth and Life Science', 'Physical Science'],
            'Filipino' => ['Wikang Filipino', 'Panitikang Filipino', 'Gramatika at Komposisyon', 'Kultura at Kasaysayan', 'Filipino sa Piling Larangan'],
            'Social Studies' => ['Philippine History', 'World History', 'Economics', 'Politics and Governance', 'Understanding Culture, Society and Politics'],
            'Technology' => ['Empowerment Technologies', 'Practical Research 1 & 2'],
            'Business' => ['Fundamentals of Accountancy, Business and Management', 'Business Finance', 'Business Marketing'],
        ];

        // Pick a random category and a title from it
        $category = $this->faker->randomElement(array_keys($subjects));
        $title = $this->faker->randomElement($subjects[$category]);
        $totalCopies = $this->faker->numberBetween(50, 200);

        return [
            'title' => $title,
            'author' => $this->faker->name(),
            'category' => $category,
            'total_copies' => $totalCopies,
            // Ensure available quantity is not more than the total
            'available_quantity' => $this->faker->numberBetween(0, $totalCopies),
        ];
    }
}