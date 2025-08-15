<?php

namespace Database\Factories;

use App\Models\PermanentRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermanentRecordFactory extends Factory
{
    protected $model = PermanentRecord::class;

    public function definition(): array
    {
        $finalAverage = $this->faker->randomFloat(2, 70, 98);
        $remarks = 'Promoted';
        if ($finalAverage >= 90) {
            $remarks = 'With Honors';
        } elseif ($finalAverage < 75) {
            $remarks = 'Retained';
        }

        return [
            'student_id' => Student::factory(),
            'final_average' => $finalAverage,
            'remarks' => $remarks,
            // Correctly find an admin or teacher user to act as the validator
            'validated_by' => User::whereIn('role', ['super_admin', 'teacher'])->inRandomOrder()->first()?->id ?? User::factory(),
        ];
    }
}
