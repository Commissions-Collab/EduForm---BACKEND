<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentBorrowBook extends Model
{
    protected $fillable = [
        'student_id',
        'book_id',
        'issued_date',
        'expected_return_date',
        'returned_date',
        'status'
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function bookInventory() {
        return $this->belongsTo(BookInventory::class);
    }
}
