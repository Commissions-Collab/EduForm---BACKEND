<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
   use HasFactory;

   protected $fillable = [
      'student_id',
      'academic_year_id',
      'grade_level',
      'section_id',
      'enrollment_status'
   ];

   public function student()
   {
      return $this->belongsTo(Student::class, 'student_id');
   }

   public function yearLevel()
   {
      return $this->belongsTo(YearLevel::class, 'grade_level');
   }

   public function section()
   {
      return $this->belongsTo(Section::class, 'section_id');
   }

   public function academicYear()
   {
      return $this->belongsTo(AcademicYear::class);
   }
}
