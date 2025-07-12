<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    public function requestBy()
    {
        return $this->belongsTo(User::class, 'request_by');
    }

    public function requestTo()
    {
        return $this->belongsTo(User::class, 'request_to');
    }
}
