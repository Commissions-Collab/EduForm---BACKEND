<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'student_id',
        'date',
        'status',
        'remarks'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
