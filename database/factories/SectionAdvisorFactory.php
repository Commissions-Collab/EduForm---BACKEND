<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\SectionAdvisor;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SectionAdvisor>
 */
class SectionAdvisorFactory extends Factory
{
    protected $model = SectionAdvisor::class;

    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'teacher_id' => Teacher::factory(),
            'academic_year_id' => AcademicYear::factory(),
        ];
    }
}
