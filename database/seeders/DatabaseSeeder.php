<?php

namespace Database\Seeders;

use App\Models\ScheduleException;
use App\Models\TeacherSubject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\SectionAdvisor;
use App\Models\Subject;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\BookInventory;
use App\Models\StudentBorrowBook;
use App\Models\StudentBMI;
use App\Models\PromotionReport;
use App\Models\AcademicCalendar;
use App\Models\Enrollment;
use App\Models\HealthProfile;
use App\Models\PermanentRecord;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Teachers & Students
        $teachers = Teacher::factory(5)->create();
        $students = Student::factory(20)->create();

        // Create Academic Year (only one)
        $academicYear = AcademicYear::updateOrCreate(
            ['name' => '2025-2026'],
            [
                'start_date' => '2025-06-01',
                'end_date' => '2026-03-31'
            ]
        );

        // Create Quarters (1st–4th)
        $quarterNames = [
            '1st Quarter',
            '2nd Quarter',
            '3rd Quarter',
            '4th Quarter'
        ];

        $quarters = collect();
        foreach ($quarterNames as $index => $name) {
            $quarters->push(
                Quarter::updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'name' => $name
                    ],
                    [
                        'start_date' => Carbon::parse($academicYear->start_date)->addMonths($index * 3),
                        'end_date' => Carbon::parse($academicYear->start_date)->addMonths(($index + 1) * 3 - 1)
                    ]
                )
            );
        }

        // Create Sections
        $sections = Section::factory(3)->create();

        // Assign Section Advisors
        foreach ($sections as $section) {
            SectionAdvisor::factory()->create([
                'section_id' => $section->id,
                'teacher_id' => $teachers->random()->id,
                'academic_year_id' => $academicYear->id,
            ]);
        }

        // Create Subjects
        $subjects = Subject::factory(6)->create();

        // Assign Teacher Subjects
        foreach ($teachers as $teacher) {
            $assignedSubjects = $subjects->random(rand(2, 4)); // each teacher gets 2-4 subjects
            foreach ($assignedSubjects as $subject) {
                TeacherSubject::factory()->create([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'academic_year_id' => $academicYear->id
                ]);
            }
        }

        // Create Schedules (avoid duplicates)
        foreach ($sections as $section) {
            foreach ($subjects as $subject) {
                foreach (['Monday', 'Tuesday'] as $day) {
                    Schedule::factory()->create([
                        'subject_id' => $subject->id,
                        'teacher_id' => $teachers->random()->id,
                        'section_id' => $section->id,
                        'academic_year_id' => $academicYear->id,
                        'quarter_id' => $quarters->random()->id,
                        'day_of_week' => $day,
                        'start_time' => '07:00:00',
                        'end_time' => '08:00:00',
                        'room' => 'Room ' . rand(1, 10),
                        'is_active' => true
                    ]);
                }
            }
        }

        foreach (Schedule::inRandomOrder()->take(5)->get() as $schedule) {
            ScheduleException::factory()->create([
                'schedule_id' => $schedule->id,
                'date' => Carbon::parse($academicYear->start_date)
                    ->addDays(rand(10, 200)), // random date within academic year
                'reason' => 'School event'
            ]);
        }

        // Create Enrollments
        foreach ($students as $student) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'section_id' => $sections->random()->id
            ]);
        }

        // Create Attendance
        foreach ($students as $student) {
            Attendance::factory(5)->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'schedule_id' => Schedule::inRandomOrder()->first()->id
            ]);
        }

        // Create Grades
        foreach ($students as $student) {
            foreach ($subjects as $subject) {
                foreach ($quarters as $quarter) {
                    Grade::factory()->create([
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'quarter_id' => $quarter->id
                    ]);
                }
            }
        }

        // Create Book Inventory & Borrow Records
        $books = BookInventory::factory(10)->create();
        foreach ($students as $student) {
            StudentBorrowBook::factory(2)->create([
                'student_id' => $student->id,
                'book_id' => $books->random()->id
            ]);
        }

        // Create Student BMI Records
        foreach ($students as $student) {
            StudentBMI::factory()->create([
                'student_id' => $student->id
            ]);
        }

        // Create Promotion Reports
        foreach ($students as $student) {
            PromotionReport::factory()->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id
            ]);
        }

        // Create Academic Calendar Events
        AcademicCalendar::factory(5)->create([
            'academic_year_id' => $academicYear->id
        ]);

        // Create Health Profiles
        foreach ($students as $student) {
            HealthProfile::factory()->create([
                'student_id' => $student->id
            ]);
        }

        // Create Permanent Records
        foreach ($students as $student) {
            PermanentRecord::factory()->create([
                'student_id' => $student->id
            ]);
        }
    }
}
