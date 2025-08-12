<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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
use App\Models\HealthProfile;
use App\Models\StudentBmi;
use App\Models\PermanentRecord;
use App\Models\PromotionReport;
use App\Models\Enrollment;
use App\Models\AcademicCalendar;
use App\Models\TeacherSubject;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create fixed academic years
        $ay1 = AcademicYear::factory()->academicYear2023_2024()->create();
        $ay2 = AcademicYear::factory()->academicYear2024_2025()->create();
        $ay3 = AcademicYear::factory()->academicYear2025_2026()->create([
            'is_current' => true
        ]);

        // Quarters for each academic year
        foreach ([$ay1, $ay2, $ay3] as $ay) {
            Quarter::factory()->firstQuarter()->create(['academic_year_id' => $ay->id]);
            Quarter::factory()->secondQuarter()->create(['academic_year_id' => $ay->id]);
            Quarter::factory()->thirdQuarter()->create(['academic_year_id' => $ay->id]);
            Quarter::factory()->fourthQuarter()->create(['academic_year_id' => $ay->id]);
        }

        $quarter1 = Quarter::where('academic_year_id', $ay3->id)
            ->where('name', '1st Quarter')
            ->first();

        // Super Admin
        User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password')
        ]);

        // Specific Teachers
        $registrarUser = User::factory()->teacher()->create([
            'email' => 'registrar@example.com',
            'password' => bcrypt('password'),
        ]);
        $registrar = Teacher::factory()->create([
            'user_id' => $registrarUser->id,
            'first_name' => 'Registrar',
            'last_name' => 'Teacher',
            'specialization' => 'Administration',
        ]);

        $peUser = User::factory()->teacher()->create([
            'email' => 'peteacher@example.com',
            'password' => bcrypt('password'),
        ]);
        $peTeacher = Teacher::factory()->create([
            'user_id' => $peUser->id,
            'first_name' => 'Physical',
            'last_name' => 'Education',
            'specialization' => 'PE',
        ]);

        // Random Teachers
        $randomTeachers = Teacher::factory()
            ->count(3)
            ->for(User::factory()->teacher())
            ->create();

        $teachers = collect([$registrar, $peTeacher])->merge($randomTeachers);

        // Students (100 total)
        $studentUsers = User::factory()
            ->count(100)
            ->student()
            ->create();

        $students = $studentUsers->map(function ($user) {
            return Student::factory()->create(['user_id' => $user->id]);
        });

        // Sections
        $sections = Section::factory()->count(3)->create([
            'academic_year_id' => $ay3->id
        ]);

        // Advisers
        foreach ($sections as $section) {
            SectionAdvisor::factory()->create([
                'section_id' => $section->id,
                'teacher_id' => $teachers->random()->id,
                'academic_year_id' => $ay3->id,
            ]);
        }

        // Subjects
        $subjects = Subject::factory()->count(7)->create();
        $peSubject = Subject::factory()->create(['name' => 'Physical Education']);
        $subjects->push($peSubject);

        // Assign PE teacher to PE subject
        TeacherSubject::create([
            'teacher_id' => $peTeacher->id,
            'subject_id' => $peSubject->id,
            'academic_year_id' => $ay3->id
        ]);

        // Assign other subjects to teachers
        $otherSubjects = $subjects->reject(fn($s) => $s->id === $peSubject->id);
        $teachers->each(function ($teacher) use ($otherSubjects, $ay3) {
            $assigned = $otherSubjects->random(rand(1, 3));
            foreach ($assigned as $subject) {
                TeacherSubject::firstOrCreate([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'academic_year_id' => $ay3->id
                ]);
            }
        });

        // Schedules
        $schedules = Schedule::factory()->count(10)->create([
            'academic_year_id' => $ay3->id,
            'quarter_id' => $quarter1->id,
            'teacher_id' => $teachers->random()->id,
            'section_id' => $sections->random()->id,
            'subject_id' => $subjects->random()->id
        ]);

        // Attendance
        Attendance::factory()->count(50)->create([
            'academic_year_id' => $ay3->id,
            'quarter_id' => $quarter1->id,
            'schedule_id' => $schedules->random()->id,
            'student_id' => $students->random()->id,
            'recorded_by' => $teachers->random()->user->id
        ]);

        // Grades — every student, every subject, 1st Quarter
        foreach ($students as $student) {
            foreach ($subjects as $subject) {
                Grade::create([
                    'academic_year_id' => $ay3->id,
                    'quarter_id' => $quarter1->id,
                    'subject_id' => $subject->id,
                    'student_id' => $student->id,
                    'recorded_by' => $teachers->random()->user->id,
                    'grade' => rand(80, 99)
                ]);
            }
        }

        // Books
        $books = BookInventory::factory()->count(10)->create();

        // Borrowed books
        StudentBorrowBook::factory()->count(15)->create([
            'student_id' => $students->random()->id,
            'book_id' => $books->random()->id
        ]);

        // Health profiles
        HealthProfile::factory()->count(20)->create([
            'student_id' => $students->random()->id,
            'updated_by' => $teachers->random()->user->id
        ]);

        // BMI
        StudentBmi::factory()->count(20)->create([
            'student_id' => $students->random()->id,
            'academic_year_id' => $ay3->id,
            'quarter_id' => $quarter1->id
        ]);

        // Permanent Records
        PermanentRecord::factory()->count(10)->create([
            'student_id' => $students->random()->id,
            'validated_by' => $teachers->random()->user->id
        ]);

        // Promotion Reports
        PromotionReport::factory()->count(10)->create([
            'student_id' => $students->random()->id,
            'academic_year_id' => $ay3->id
        ]);

        // Enrollments
        Enrollment::factory()->count(100)->create([
            'student_id' => $students->random()->id,
            'academic_year_id' => $ay3->id,
            'section_id' => $sections->random()->id
        ]);

        // Academic Calendar
        AcademicCalendar::factory()->count(10)->create([
            'academic_year_id' => $ay3->id
        ]);
    }
}
