<?php

namespace Database\Seeders;

use App\Models\BookInventory;
use App\Models\Student;
use App\Models\StudentBorrowBook;
use Illuminate\Database\Seeder;

class StudentBorrowBookSeeder extends Seeder
{
    public function run(): void
    {
        // Get all created students and books to create borrowing records
        $allStudents = Student::all();
        $allBooks = BookInventory::all();

        // Ensure we have students and books before proceeding
        if ($allStudents->isNotEmpty() && $allBooks->isNotEmpty()) {
            // Create 150 random book borrowing records
            for ($i = 0; $i < 150; $i++) {
                $student = $allStudents->random();
                $book = $allBooks->random();

                // Only create a borrow record if the book is available
                if ($book->available_quantity > 0) {
                    StudentBorrowBook::factory()->create([
                        'student_id' => $student->id,
                        'book_id' => $book->id,
                    ]);

                    // After creating the borrow record, decrement the available quantity
                    $book->decrement('available_quantity');
                }
            }
        }
    }
}