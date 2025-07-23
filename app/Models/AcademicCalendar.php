<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicCalendar extends Model
{
    protected $fillable = [
        'academic_year_id',
        'date',
        'type',
        'title',
        'description',
        'is_class_day'
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_class_day' => 'boolean',
        ];
    }

    // Relationships
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    // Scopes
    public function scopeClassDays($query)
    {
        return $query->where('is_class_day', true);
    }

    public function scopeHolidays($query)
    {
        return $query->where('type', 'holiday');
    }
}
