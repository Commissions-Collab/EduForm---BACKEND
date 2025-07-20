<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = \App\Models\Schedule::class;

    protected static $timeSlots = [
        ['08:00:00', '09:00:00'],
        ['09:00:00', '10:00:00'],
        ['10:00:00', '11:00:00'],
        ['11:00:00', '12:00:00'],
        ['13:00:00', '14:00:00'],
        ['14:00:00', '15:00:00'],
        ['15:00:00', '16:00:00'],
    ];

    protected static $usedSlots = [];

    public function definition(): array
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // Ensure required models exist
        $teacher = User::where('role', 'teacher')->inRandomOrder()->first();
        $subject = Subject::inRandomOrder()->first();
        $yearLevel = YearLevel::inRandomOrder()->first();

        // Prevent null ID issues
        if (!$teacher || !$subject || !$yearLevel) {
            throw new \Exception('Required related models (teacher, subject, year level) are missing.');
        }

        // Loop to find a unique slot
        do {
            $day = $this->faker->randomElement($days);
            $slot = $this->faker->randomElement(self::$timeSlots);
            $key = $day . '_' . $slot[0] . '_' . $slot[1];
        } while (in_array($key, self::$usedSlots));

        self::$usedSlots[] = $key;

        return [
            'day' => $day,
            'start_time' => $slot[0],
            'end_time' => $slot[1],
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'year_level_id' => $yearLevel->id,
            'admin_id' => 1, // You can customize this
        ];
    }
}
