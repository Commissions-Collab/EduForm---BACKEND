<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    public function user() {
       return $this->belongsTo(User::class, 'student_id');
    }

    public function subject() {
       return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function recordedBy() {
       return $this->belongsTo(User::class, 'recorded_by');
    }
}
