<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
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
      'image'
   ];
}
