<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthProfile extends Model
{
   use HasFactory;
   
    public function student() {
       return $this->belongsTo(Student::class, 'student_id');
    }

    public function updatedBy() {
       return $this->belongsTo(User::class, 'updated_by');
    }
}
