<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YearLevel extends Model
{
   use HasFactory, SoftDeletes;

   protected $fillable = [
      'name',
      'code',
      'sort_order'
   ];

   // Relationships
   public function sections()
   {
      return $this->hasMany(Section::class);
   }

   public function subjects()
   {
      return $this->belongsToMany(Subject::class, 'year_level_subjects');
   }

   public function yearLevelSubjects()
    {
        return $this->hasMany(YearLevelSubject::class);
    }

   // Scopes
   public function scopeOrdered($query)
   {
      return $query->orderBy('sort_order');
   }
}
