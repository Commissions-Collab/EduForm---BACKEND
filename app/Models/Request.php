<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    protected $fillable = [
        'request_to',
        'request_type',
        'email',
        'password',
        'role',
        'LRN',
        'first_name',
        'middle_name',
        'last_name',
        'birthday',
        'gender',
        'parents_fullname',
        'relationship_to_student',
        'parents_number',
        'parents_email',
        'image',
        'status',
    ];


    public function requestBy()
    {
        return $this->belongsTo(User::class, 'request_by');
    }

    public function requestTo()
    {
        return $this->belongsTo(User::class, 'request_to');
    }
}
