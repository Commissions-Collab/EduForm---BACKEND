<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quarter extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'name',
        'quarter_number',
        'start_date',
        'end_date',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function teacherSubjects()
    {
        return $this->hasMany(TeacherSubject::class);
    }

    public function getDurationInDays()
    {
        return Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date)) + 1;
    }

    public function getSchoolDaysCount()
    {
        // Basic version: same as duration (excluding weekends/holidays is optional logic)
        return Carbon::parse($this->start_date)
            ->diffInDaysFiltered(function (Carbon $date) {
                // Exclude weekends (Saturday and Sunday)
                return !$date->isWeekend();
            }, Carbon::parse($this->end_date)) + 1;
    }
}
