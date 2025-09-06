<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YearLevelSubject extends Model
{
    use HasFactory;

    protected $fillable = ['year_level_id', 'subject_id'];

    public function yearLevel()
    {
        return $this->belongsTo(YearLevel::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
