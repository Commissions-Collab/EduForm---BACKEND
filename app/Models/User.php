<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Gender;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
   /** @use HasFactory<\Database\Factories\UserFactory> */
   use HasApiTokens, HasFactory, Notifiable;

   /**
    * The attributes that are mass assignable.
    *
    * @var list<string>
    */
   protected $fillable = [
      'email',
      'password',
      'role',
   ];

   /**
    * The attributes that should be hidden for serialization.
    *
    * @var list<string>
    */
   protected $hidden = [
      'password',
      'remember_token',
   ];

   /**
    * Get the attributes that should be cast.
    *
    * @return array<string, string>
    */
   protected function casts(): array
   {
      return [
         'email_verified_at' => 'datetime',
         'password' => 'hashed',
         'role' => UserRole::class,
         'gender' => Gender::class,
      ];
   }

   // Helper methods for role checking
   public function isSuperAdmin(): bool
   {
      return $this->role === UserRole::SUPER_ADMIN;
   }

   public function isTeacher(): bool
   {
      return $this->role === UserRole::TEACHER;
   }

   public function isStudent(): bool
   {
      return $this->role === UserRole::STUDENT;
   }

   public  function yearLevels()
   {
      return $this->hasMany(YearLevel::class, 'admin_id');
   }

   public function sections()
   {
      return $this->hasMany(Section::class, 'admin_id');
   }

   public function enrollments()
   {
      return $this->hasMany(Enrollment::class, 'student_id');
   }

   public function attendances()
   {
      return $this->hasMany(Attendance::class, 'student_id');
   }

   public function grades()
   {
      return $this->hasMany(Grade::class, 'student_id');
   }

   public function recorderBy()
   {
      return $this->hasMany(Grade::class, 'recorded_by');
   }

   public function permanentRecords()
   {
      return $this->hasMany(PermanentRecord::class, 'student_id');
   }

   public function validatedBy()
   {
      return $this->hasMany(PermanentRecord::class, 'validated_by');
   }

   public function healthProfiles()
   {
      return $this->hasMany(HealthProfile::class, 'student_id');
   }

   public function updateHealthProfiles()
   {
      return $this->hasMany(HealthProfile::class, 'updated_by');
   }

   public function bookInventories()
   {
      return $this->hasMany(BookInventory::class, 'student_id');
   }

   public function certificateRecords()
   {
      return $this->hasMany(CertificateRecord::class, 'student_id');
   }

   public function certificateIssuedBy()
   {
      return $this->hasMany(CertificateRecord::class, 'issued_by');
   }

   public function requestsTo()
   {
      return $this->hasMany(Request::class, 'request_to');
   }
   public function teacher(): HasOne
   {
      return $this->hasOne(Teacher::class);
   }

   public function student() {
      return $this->hasOne(Student::class);
   }

   public function schedule() {
      return $this->hasMany(Schedule::class, 'admin_id');
   }
}
