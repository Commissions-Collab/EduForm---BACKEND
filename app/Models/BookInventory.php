<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookInventory extends Model
{
    public function user() {
       return $this->belongsTo(User::class, 'student_id');
    }
}
