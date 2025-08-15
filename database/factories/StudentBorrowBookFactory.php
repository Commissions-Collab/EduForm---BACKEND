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
        $borrowDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $dueDate = Carbon::parse($borrowDate)->addWeeks(2); // Standard 2-week borrowing period
        $status = $this->faker->randomElement(['issued', 'returned', 'overdue']);

        $returnDate = null;

        // If status is 'returned', generate a return date
        if ($status === 'returned') {
            // Book could be returned on time or slightly late
            $returnDate = $this->faker->dateTimeBetween($borrowDate, $dueDate->copy()->addDays(5));
        }

        // If status is 'overdue', ensure the due date is in the past and there's no return date
        if ($status === 'overdue') {
            $borrowDate = $this->faker->dateTimeBetween('-4 months', '-3 weeks');
            $dueDate = Carbon::parse($borrowDate)->addWeeks(2);
            $returnDate = null; // An overdue book has not been returned
        }

        return [
            'student_id' => Student::factory(),
            'book_id' => BookInventory::factory(),
            'borrow_date' => $borrowDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'return_date' => $returnDate ? $returnDate->format('Y-m-d') : null,
            'status' => $status,
        ];
    }
}
