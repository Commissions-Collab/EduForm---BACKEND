<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentUsers = User::where('role', 'student')->get();
        $sections = Section::all();
        
        foreach ($studentUsers as $user) {
            Student::factory()->create([
                'user_id' => $user->id,
                'section_id' => $sections->random()->id,
            ]);
        }
    }
}
