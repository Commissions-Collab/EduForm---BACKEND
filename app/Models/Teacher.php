<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_id',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'hire_date',
        'employment_status'
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function teacherSubjects()
    {
        return $this->hasMany(TeacherSubject::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects')
                    ->withPivot('academic_year_id')
                    ->withTimestamps();
    }

    public function bookInventories () {
        return $this->hasMany(BookInventory::class);
    }

    public function getSubjectsForYear($academicYearId)
    {
        return $this->subjects()
                    ->wherePivot('academic_year_id', $academicYearId)
                    ->get();
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function sectionAdvisors()
    {
        return $this->hasMany(SectionAdvisor::class);
    }

    public function advisedSections()
    {
        return $this->belongsToMany(Section::class, 'section_advisors')
                    ->withPivot('academic_year_id')
                    ->withTimestamps();
    }

    public function attendances()
    {
        return $this->hasManyThrough(Attendance::class, Schedule::class);
    }

    // Helper methods
    public function fullName()
    {
        return trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name);
    }

    public function currentSubjects()
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        return $this->subjects()->wherePivot('academic_year_id', $currentYear?->id);
    }

    public function currentSchedules()
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        return $this->schedules()->where('academic_year_id', $currentYear?->id);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('employment_status', 'active');
    }
}
