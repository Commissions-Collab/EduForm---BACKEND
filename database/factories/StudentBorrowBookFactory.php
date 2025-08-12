<?php

namespace Database\Factories;

use App\Models\StudentBorrowBook;
use App\Models\Student;
use App\Models\BookInventory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class StudentBorrowBookFactory extends Factory
{
    protected $model = StudentBorrowBook::class;

    public function definition(): array
    {
        $issuedDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $expectedReturnDate = Carbon::parse($issuedDate)->addWeeks(2); // 2 weeks borrowing period
        $status = $this->faker->randomElement(['issued', 'returned', 'overdue']);
        
        $returnedDate = null;
        if ($status === 'returned') {
            $returnedDate = $this->faker->dateTimeBetween($issuedDate, $expectedReturnDate->addDays(7));
        }

        return [
            'student_id' => Student::factory(),
            'book_id' => BookInventory::factory(),
            'issued_date' => $issuedDate->format('Y-m-d'),
            'returned_date' => $returnedDate ? $returnedDate->format('Y-m-d') : null,
            'expected_return_date' => $expectedReturnDate->format('Y-m-d'),
            'status' => $status,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'issued',
            'returned_date' => null,
        ]);
    }

    public function returned(): static
    {
        return $this->state(function (array $attributes) {
            $issuedDate = Carbon::parse($attributes['issued_date'] ?? $this->faker->dateTimeBetween('-2 months', '-1 month'));
            $expectedReturn = Carbon::parse($attributes['expected_return_date'] ?? $issuedDate->copy()->addWeeks(2));
            
            return [
                'status' => 'returned',
                'returned_date' => $this->faker->dateTimeBetween($issuedDate, $expectedReturn->addDays(3))->format('Y-m-d'),
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            $issuedDate = $this->faker->dateTimeBetween('-3 months', '-1 month');
            $expectedReturn = Carbon::parse($issuedDate)->addWeeks(2);
            
            return [
                'issued_date' => $issuedDate->format('Y-m-d'),
                'expected_return_date' => $expectedReturn->format('Y-m-d'),
                'status' => 'overdue',
                'returned_date' => null,
            ];
        });
    }
}