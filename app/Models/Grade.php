<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
   use HasFactory;
   
   protected $fillable = [
      'student_id',
      'subject_id',
      'quarter_id',
      'academic_year_id',
      'grade',
      'recorded_by'
   ];

   public function student()
   {
      return $this->belongsTo(Student::class, 'student_id');
   }

   public function subject()
   {
      return $this->belongsTo(Subject::class, 'subject_id');
   }

   public function recordedBy()
   {
      return $this->belongsTo(User::class, 'recorded_by');
   }

   public function quarter() {
      return $this->belongsTo(Quarter::class, 'quarter_id');
   }

   public function academicYear() {
      return $this->belongsTo(AcademicYear::class);
   }
}
