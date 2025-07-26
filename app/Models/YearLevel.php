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

   // Scopes
   public function scopeOrdered($query)
   {
      return $query->orderBy('sort_order');
   }
}
