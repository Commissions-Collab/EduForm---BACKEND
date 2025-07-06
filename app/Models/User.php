<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }
    public  function yearLevels() {
       return $this->hasMany(YearLevel::class, 'admin_id');
    }

    public function sections() {
       return $this->hasMany(Section::class, 'admin_id');
    }

    public function enrollments(){
       return $this->hasMany(Enrollment::class, 'student_id');
    }

    public function attendances(){
       return $this->hasMany(Attendance::class, 'student_id');
    }

    public function grades(){
       return $this->hasMany(Grade::class, 'student_id');
    }

    public function recorderBy(){
       return $this->hasMany(Grade::class, 'recorded_by');
    }

    public function permanentRecords(){
       return $this->hasMany(PermanentRecord::class, 'student_id');
    }

    public function validatedBy(){
       return $this->hasMany(PermanentRecord::class, 'validated_by');
    }

    public function healthProfiles(){
       return $this->hasMany(HealthProfile::class, 'student_id');
    }

    public function updateHealthProfiles(){
       return $this->hasMany(HealthProfile::class, 'updated_by');
    }

    public function bookInventories() {
       return $this->hasMany(BookInventory::class, 'student_id');
    }

    public function certificateRecords() {
       return $this->hasMany(CertificateRecord::class, 'student_id');
    }

    public function certificateIssuedBy() {
       return $this->hasMany(CertificateRecord::class, 'issued_by');
    }
}
