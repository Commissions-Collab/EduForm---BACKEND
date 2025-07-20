<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    public function teacher() {
        return $this->belongsTo(Teacher::class, 'subject_id');
    }

    public function subject() {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function year_level() {
        return $this->belongsTo(YearLevel::class, 'year_level_id');
    }
}
