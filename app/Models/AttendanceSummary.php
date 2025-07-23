<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSummary extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'academic_year_id',
        'month',
        'year',
        'total_classes',
        'present_count',
        'absent_count',
        'late_count',
        'excused_count',
        'attendance_percentage'
    ];

    protected function casts(): array
    {
        return [
            'attendance_percentage' => 'decimal:2',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    // Helper methods
    public function calculatePercentage()
    {
        if ($this->total_classes > 0) {
            return ($this->present_count / $this->total_classes) * 100;
        }
        return 0;
    }

    // Scopes
    public function scopeForMonth($query, $month, $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }
}
