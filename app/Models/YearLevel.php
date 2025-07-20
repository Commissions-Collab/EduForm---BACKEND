<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YearLevel extends Model
{
   use HasFactory;

   protected $fillable = [
      'admin_id',
      'name'
   ];

    public function user() {
       return $this->belongsTo(User::class, 'admin_id');
    }

    public function sections () {
       return $this->hasMany(Section::class, 'year_level_id');
    }

    public function enrollments() {
       return $this->hasMany(Enrollment::class, 'grade_level');
    }

    public function promotionReport() {
       return $this->hasMany(PromotionReport::class, 'year_level_id');
    }

    public function schedule() {
        return $this->hasMany(Schedule::class, 'teacher_id');
    }
}
