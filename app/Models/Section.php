<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    public function user() {
       return $this->belongsTo(User::class, 'admin_id');
    }

    public function yearLevel() {
       return $this->belongsTo(YearLevel::class, 'year_level_id');
    }

    public function enrollments() {
        return $this->hasMany(Enrollment::class, 'section_id');
    }
}
