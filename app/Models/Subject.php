<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    public function grades() {
       return $this->hasMany(Grade::class, 'subject_id');
    }

    public function schedule() {
        return $this->hasMany(Schedule::class, 'teacher_id');
    }
}
