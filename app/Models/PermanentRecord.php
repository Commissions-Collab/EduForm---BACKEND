<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermanentRecord extends Model
{
    public function user() {
       return $this->belongsTo(User::class, 'student_id');
    }

    public function validatedBy() {
       return $this->belongsTo(User::class, 'validated_by');
    }
}
