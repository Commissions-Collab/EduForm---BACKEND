<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookInventory extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'title',
        'author',
        'category',
        'total_copies',
        'available_quantity'
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
