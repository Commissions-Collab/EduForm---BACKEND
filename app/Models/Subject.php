<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'adivisor_id'
    ];

    public function grades() {
       return $this->hasMany(Grade::class, 'subject_id');
    }

    public function schedules() {
        return $this->hasMany(Schedule::class, 'subject_id');
    }

    public function teacher () {
        return $this->belongsTo(Teacher::class, 'advisor_id');
    }
}
