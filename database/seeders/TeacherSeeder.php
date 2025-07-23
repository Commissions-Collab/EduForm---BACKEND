<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teacherUsers = User::where('role', 'teacher')->get();
        
        foreach ($teacherUsers as $user) {
            Teacher::factory()->create([
                'user_id' => $user->id,
            ]);
        }
    }
}
