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
       return $this->belongsTo(User::class, 'user_id');
    }
    
    public function promotionReport() {
       return $this->hasMany(PromotionReport::class, 'year_level_id');
    }

    public function schedule() {
        return $this->hasMany(Schedule::class, 'teacher_id');
    }
}
