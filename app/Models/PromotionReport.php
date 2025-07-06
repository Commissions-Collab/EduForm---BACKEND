<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionReport extends Model
{
    public function user() {
       return $this->belongsTo(User::class, 'student_id');
    }

    public function yearLevel() {
       return $this->belongsTo(YearLevel::class, 'year_level_id');
    }
}
