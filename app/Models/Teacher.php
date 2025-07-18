<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Teacher extends Model
{
     protected $fillable = [
        'user_id',
        'name',
        'is_advisor_id',
        'subject',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class,'is_advisor_id');
    }

}
