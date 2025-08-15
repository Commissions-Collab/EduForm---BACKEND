<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentBorrowBook extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'student_id',
        'book_id',
        'borrow_date',
        'due_date',
        'return_date',
        'status'
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function bookInventory() {
        return $this->belongsTo(BookInventory::class, 'book_id');
    }
}
