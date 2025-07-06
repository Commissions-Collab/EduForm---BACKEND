<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthProfile extends Model
{
    public function user() {
       return $this->belongsTo(User::class, 'student_id');
    }

    public function updatedBy() {
       return $this->belongsTo(User::class, 'updated_by');
    }
}
