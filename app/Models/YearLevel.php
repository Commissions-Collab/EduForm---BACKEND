<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YearLevel extends Model
{
    public function user() {
       return $this->belongsTo(User::class, 'user_id');
    }
    
    public function promotionReport() {
       return $this->hasMany(PromotionReport::class, 'year_level_id');
    }
}
