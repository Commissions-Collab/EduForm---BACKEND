<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'year_level_id',
        'name'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function yearLevel()
    {
        return $this->belongsTo(YearLevel::class, 'year_level_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'student_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'section_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'schedule_id');
    }
}
