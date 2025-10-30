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
        'isbn',
        'publisher',
        'total_copies',
        'available_quantity'
    ];

    protected $casts = [
        'total_copies' => 'integer',
        'available_quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the teacher that created this inventory
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the subject associated with this book
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get all student borrow records for this book
     */
    public function studentBorrowBooks()
    {
        return $this->hasMany(StudentBorrowBook::class, 'book_id');
    }

    /**
     * Get the count of issued copies
     */
    public function getIssuedCountAttribute()
    {
        return $this->studentBorrowBooks()
            ->where('status', 'issued')
            ->count();
    }

    /**
     * Get the count of overdue copies
     */
    public function getOverdueCountAttribute()
    {
        return $this->studentBorrowBooks()
            ->where('status', '!=', 'returned')
            ->where('return_date', '<', now())
            ->count();
    }

    /**
     * Scope to get available books
     */
    public function scopeAvailable($query)
    {
        return $query->where('available_quantity', '>', 0);
    }

    /**
     * Scope to get books with overdue items
     */
    public function scopeWithOverdue($query)
    {
        return $query->whereHas('studentBorrowBooks', function ($q) {
            $q->where('status', '!=', 'returned')
                ->where('return_date', '<', now());
        });
    }
}
