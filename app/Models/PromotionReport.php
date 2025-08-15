<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionReport extends Model
{
   use HasFactory;
   
    public function student() {
       return $this->belongsTo(Student::class, 'student_id');
    }

    public function yearLevel() {
       return $this->belongsTo(YearLevel::class, 'year_level_id');
    }
}
