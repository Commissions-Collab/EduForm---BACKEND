<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentBmi extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year_id',
        'quarter_id',
        'recorded_at',
        'height_cm',
        'weight_kg',
        'bmi',
        'bmi_category',
        'remarks',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'student_id');
    }


    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function quarter()
    {
        return $this->belongsTo(Quarter::class);
    }
}
