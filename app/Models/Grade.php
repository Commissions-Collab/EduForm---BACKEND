<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
   protected $fillable = [
      'student_id',
      'subject_id',
      'quarter',
      'grade',
      'recorded_by'
   ];

   public function user()
   {
      return $this->belongsTo(User::class, 'student_id');
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
}
