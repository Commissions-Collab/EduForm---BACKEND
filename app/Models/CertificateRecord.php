<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateRecord extends Model
{
    public function user() {
       return $this->belongsTo(User::class, 'student_id');
    }

    public function issuedBy() {
       return $this->belongsTo(User::class, 'issued_by');
    }
}
