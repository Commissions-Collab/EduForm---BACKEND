<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'day',
        'start_time',
        'end_time',
        'subject_id',
        'section_id',
        'teacher_id',
        'year_level_id',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function year_level()
    {
        return $this->belongsTo(YearLevel::class, 'year_level_id');
    }
    public function section()
    {
        return $this->belongsTo(Section::class,'section_id');
    }
}
