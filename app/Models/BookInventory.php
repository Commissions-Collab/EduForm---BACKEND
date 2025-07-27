<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookInventory extends Model
{
    protected $fillable = [
        'title',
        'teacher_id',
        'subject_id',
        'total_copies',
        'available'
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function studentBorrowBooks() {
        return $this->hasMany(StudentBorrowBook::class, 'book_id');
    }
}
