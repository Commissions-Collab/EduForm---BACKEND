<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'year_level_id',
        'academic_year_id',
        'name',
        'capacity'
    ];
    
    // Relationships
    public function yearLevel()
    {
        return $this->belongsTo(YearLevel::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function sectionAdvisors()
    {
        return $this->hasMany(SectionAdvisor::class);
    }

    public function advisors()
    {
        return $this->belongsToMany(Teacher::class, 'section_advisors')
                    ->withPivot('academic_year_id')
                    ->withTimestamps();
    }

    // Helper methods
    public function currentAdvisor()
    {
        return $this->belongsToMany(Teacher::class, 'section_advisors')
                    ->wherePivot('academic_year_id', $this->academic_year_id)
                    ->first();
    }

    public function activeStudents()
    {
        return $this->students()->where('enrollment_status', 'enrolled');
    }
}
