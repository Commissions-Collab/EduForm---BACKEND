<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'section_id',
        'lrn',
        'student_id',
        'first_name',
        'middle_name',
        'last_name',
        'birthday',
        'gender',
        'address',
        'phone',
        'parent_guardian_name',
        'relationship_to_student',
        'parent_guardian_phone',
        'parent_guardian_email',
        'photo',
        'enrollment_date',
        'enrollment_status'
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'enrollment_date' => 'date',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceSummaries()
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class, 'student_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function bmis()
    {
        return $this->hasMany(StudentBmi::class, 'student_id', 'user_id');
    }

    public function studentBorrowBooks()
    {
        return $this->hasMany(StudentBorrowBook::class);
    }

    // Helper methods
    public function fullName()
    {
        return trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name);
    }

    public function age()
    {
        return $this->birthday->age;
    }

    public function currentAttendanceRate($subjectId = null)
    {
        $query = $this->attendances()
            ->whereHas('schedule', function ($q) {
                $currentYear = AcademicYear::where('is_current', true)->first();
                $q->where('academic_year_id', $currentYear?->id);
            });

        if ($subjectId) {
            $query->whereHas('schedule', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            });
        }

        $total = $query->count();
        $present = $query->where('status', 'present')->count();

        return $total > 0 ? ($present / $total) * 100 : 0;
    }

    // Scopes
    public function scopeEnrolled($query)
    {
        return $query->where('enrollment_status', 'enrolled');
    }
}
