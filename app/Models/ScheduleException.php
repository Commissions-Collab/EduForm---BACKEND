<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleException extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'schedule_id',
        'date',
        'type',
        'new_start_time',
        'new_end_time',
        'new_room',
        'reason'
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'new_start_time' => 'datetime:H:i',
            'new_end_time' => 'datetime:H:i',
        ];
    }

    // Relationships
    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }
}
