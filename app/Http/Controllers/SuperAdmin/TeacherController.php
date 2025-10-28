<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\SectionAdvisor;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function getFilterOptions(Request $request)
    {
        // Fetch the current academic year
        $currentYear = AcademicYear::where('is_current', true)->first()
            ?? AcademicYear::latest('id')->first();

        if (!$currentYear) {
            return response()->json([
                'academic_year' => null,
                'quarters' => [],
                'subjects' => [],
                'sections' => [],
            ], 200);
        }

        // Quarters for this year
        $quarters = Quarter::where('academic_year_id', $currentYear->id)
            ->orderBy('id')
            ->get(['id', 'name']);

        // All subjects
        $subjects = Subject::orderBy('name')->get(['id', 'name']);

        // All sections for this year
        $sections = Section::where('academic_year_id', $currentYear->id)
            ->orderBy('name')
            ->get()
            ->map(function ($section) use ($currentYear) {
                $advisor = $section->advisors()
                    ->wherePivot('academic_year_id', $currentYear->id)
                    ->first();

                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'adviser' => $advisor
                        ? trim($advisor->first_name . ' ' . ($advisor->middle_name ? $advisor->middle_name . ' ' : '') . $advisor->last_name)
                        : null,
                ];
            });

        return response()->json([
            'academic_year' => [
                'id' => $currentYear->id,
                'name' => $currentYear->name,
                'is_current' => $currentYear->is_current,
            ],
            'quarters' => $quarters,
            'subjects' => $subjects,
            'sections' => $sections,
        ]);
    }


    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $page = $request->get('page', 1);

        $teachers = Teacher::with(['user:id,email', 'sectionAdvisors'])
            ->select(['id', 'user_id', 'employee_id', 'first_name', 'middle_name', 'last_name', 'gender', 'address', 'phone', 'specialization', 'hired_date', 'employment_status'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $teachers
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',

                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'gender' => 'required|string|in:Male,Female,Other',
                'address' => 'nullable|string|max:500',
                'phone' => 'nullable|string|regex:/^09[0-9]{9}$/',
                'hired_date' => 'nullable|date',
                'specialization' => 'nullable|string|max:255',
            ]);

            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'teacher',
                'is_verified' => true
            ]);

            $teacher = Teacher::create([
                'user_id' => $user->id,
                'employee_id' => strtoupper('TEACHER-' . str_pad((Teacher::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT)),
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'],
                'last_name' => $validated['last_name'],
                'gender' => $validated['gender'],
                'address' => $validated['address'],
                'phone' => $validated['phone'],
                'specialization' => $validated['specialization'],
                'hired_date' => $validated['hired_date'],
                'employment_status' => 'active',
            ]);

            // Load relationships for response
            $teacher->load(['user:id,email']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher registered successfully',
                'data' => $teacher
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $teacher = Teacher::with('user')->findOrFail($id);

            $validated = $request->validate([
                'email' => [
                    'required',
                    'email',
                    Rule::unique('users', 'email')->ignore($teacher->user_id)
                ],
                'password' => 'nullable|string|min:8|confirmed',

                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'gender' => 'required|string|in:Male,Female,Other',
                'address' => 'nullable|string|max:500',
                'phone' => 'nullable|string|regex:/^09[0-9]{9}$/',
                'hired_date' => 'nullable|date',
                'specialization' => 'nullable|string|max:255',
                'status' => 'required|string|in:active,inactive,terminated'
            ]);

            $user = User::findOrFail($teacher->user_id);

            // Prepare user update data
            $userUpdateData = ['email' => $validated['email']];

            // Only update password if provided
            if (!empty($validated['password'])) {
                $userUpdateData['password'] = Hash::make($validated['password']);
            }

            $user->update($userUpdateData);

            $teacher->update([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'],
                'last_name' => $validated['last_name'],
                'gender' => $validated['gender'],
                'address' => $validated['address'],
                'phone' => $validated['phone'],
                'specialization' => $validated['specialization'],
                'hired_date' => $validated['hired_date'],
                'employment_status' => $validated['status'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher updated successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function delete(string $id)
    {
        try {
            $teacher = Teacher::findOrFail($id);

            // Check if teacher has active schedules or is an advisor
            $hasActiveSchedules = Schedule::where('teacher_id', $id)->where('is_active', true)->exists();
            $isAdvisor = SectionAdvisor::where('teacher_id', $id)->exists();

            if ($hasActiveSchedules || $isAdvisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete teacher. Teacher has active schedules or is assigned as an advisor.'
                ], 409);
            }

            DB::beginTransaction();

            // Delete associated user
            if ($teacher->user_id) {
                User::find($teacher->user_id)?->delete();
            }

            $teacher->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher deleted successfully'
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function createTeacherSchedule(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
                'academic_year_id' => 'required|exists:academic_years,id',
                'schedules' => 'required|array|min:1',
                'schedules.*.subject_id' => 'required|exists:subjects,id',
                'schedules.*.section_id' => 'required|exists:sections,id',
                'schedules.*.quarter_id' => 'required|exists:quarters,id',
                'schedules.*.day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
                'schedules.*.start_time' => 'required|date_format:H:i',
                'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
                'schedules.*.room' => 'nullable|string|max:255',
            ]);

            $teacherId = $validated['teacher_id'];
            $academicYearId = $validated['academic_year_id'];
            $createdSchedules = [];

            foreach ($validated['schedules'] as $index => $scheduleData) {
                // Check time overlap
                $conflict = Schedule::where('academic_year_id', $academicYearId)
                    ->where('day_of_week', $scheduleData['day_of_week'])
                    ->where(function ($query) use ($scheduleData) {
                        // Check for time overlap: (StartA < EndB) AND (EndA > StartB)
                        $query->where('start_time', '<', $scheduleData['end_time'])
                            ->where('end_time', '>', $scheduleData['start_time']);
                    })
                    ->where(function ($query) use ($scheduleData, $teacherId) {
                        // Check for conflict with the same teacher OR section OR room
                        $query->where('teacher_id', $teacherId)
                            ->orWhere('section_id', $scheduleData['section_id']);

                        if (!empty($scheduleData['room'])) {
                            $query->orWhere('room', $scheduleData['room']);
                        }
                    })
                    ->exists();

                if ($conflict) {
                    DB::rollBack();

                    // Get subject and section names for better error message
                    $subject = Subject::find($scheduleData['subject_id']);
                    $section = Section::find($scheduleData['section_id']);

                    return response()->json([
                        'success' => false,
                        'message' => "Schedule conflict detected for " . ($subject ? $subject->name : 'Subject') .
                            " in " . ($section ? $section->name : 'Section') .
                            " on {$scheduleData['day_of_week']} at {$scheduleData['start_time']}–{$scheduleData['end_time']}. " .
                            "The teacher, section, or room is already booked.",
                        'conflict_schedule_index' => $index + 1
                    ], 409);
                }

                // Create schedule if no conflict
                $createdSchedules[] = Schedule::create([
                    'teacher_id' => $teacherId,
                    'academic_year_id' => $academicYearId,
                    'subject_id' => $scheduleData['subject_id'],
                    'section_id' => $scheduleData['section_id'],
                    'quarter_id' => $scheduleData['quarter_id'],
                    'day_of_week' => $scheduleData['day_of_week'],
                    'start_time' => $scheduleData['start_time'],
                    'end_time' => $scheduleData['end_time'],
                    'room' => $scheduleData['room'] ?? null,
                    'is_active' => true,
                ]);

                // Auto-create TeacherSubject record if it doesn't exist
                TeacherSubject::firstOrCreate(
                    [
                        'teacher_id' => $teacherId,
                        'subject_id' => $scheduleData['subject_id'],
                        'section_id' => $scheduleData['section_id'],
                        'academic_year_id' => $academicYearId,
                        'quarter_id' => $scheduleData['quarter_id'],
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdSchedules) > 1 ?
                    'Schedules created successfully.' :
                    'Schedule created successfully.',
                'data' => $createdSchedules,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating teacher schedule: ' . $e->getMessage(), [
                'teacher_id' => $request->input('teacher_id'),
                'academic_year_id' => $request->input('academic_year_id'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating schedules.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function assignTeacherSectionAdviser(Request $request)
    {
        try {
            $validated = $request->validate([
                'section_id' => 'required|exists:sections,id',
                'teacher_id' => 'required|exists:teachers,id',
                'academic_year_id' => 'required|exists:academic_years,id'
            ]);

            $hasAdvisory = SectionAdvisor::where('teacher_id', $validated['teacher_id'])
                ->where('academic_year_id', $validated['academic_year_id'])
                ->exists();

            if ($hasAdvisory) {
                $teacher = Teacher::find($validated['teacher_id']);
                $academicYear = AcademicYear::find($validated['academic_year_id']);

                return response()->json([
                    'success' => false,
                    'message' => "{$teacher->fullName()} already has an advisory for {$academicYear->name}.",
                ], 409);
            }

            $existingAdviser = SectionAdvisor::where('section_id', $validated['section_id'])
                ->where('academic_year_id', $validated['academic_year_id'])
                ->exists();

            if ($existingAdviser) {
                $section = Section::find($validated['section_id']);
                $academicYear = AcademicYear::find($validated['academic_year_id']);

                return response()->json([
                    'success' => false,
                    'message' => "{$section->name} already has an adviser for {$academicYear->name}.",
                ], 409);
            }

            $advisor = SectionAdvisor::create([
                'section_id' => $validated['section_id'],
                'teacher_id' => $validated['teacher_id'],
                'academic_year_id' => $validated['academic_year_id'],
            ]);

            $advisor->load(['section', 'teacher', 'academicYear']);

            return response()->json([
                'success' => true,
                'message' => 'Teacher successfully assigned as section adviser.',
                'data' => $advisor,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            Log::error('Error assigning teacher adviser', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
            ], 500);
        }
    }

    public function assignTeacherSubjects(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
                'academic_year_id' => 'required|exists:academic_years,id',
                'assignments' => 'required|array|min:1',
                'assignments.*.subject_id' => 'required|exists:subjects,id',
                'assignments.*.section_id' => 'nullable|exists:sections,id',
                'assignments.*.quarter_id' => 'nullable|exists:quarters,id',
            ]);

            $teacherId = $validated['teacher_id'];
            $academicYearId = $validated['academic_year_id'];

            $createdAssignments = [];

            foreach ($validated['assignments'] as $data) {
                // Check if the exact combination already exists
                $exists = \App\Models\TeacherSubject::where('teacher_id', $teacherId)
                    ->where('subject_id', $data['subject_id'])
                    ->where('section_id', $data['section_id'] ?? null)
                    ->where('academic_year_id', $academicYearId)
                    ->where('quarter_id', $data['quarter_id'] ?? null)
                    ->exists();

                if ($exists) {
                    $subject = \App\Models\Subject::find($data['subject_id']);
                    $section = isset($data['section_id']) ? \App\Models\Section::find($data['section_id']) : null;
                    $quarter = isset($data['quarter_id']) ? \App\Models\Quarter::find($data['quarter_id']) : null;
                    
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Subject '{$subject->name}'" . 
                            ($section ? " in section '{$section->name}'" : '') .
                            ($quarter ? " for {$quarter->name}" : '') .
                            " is already assigned to this teacher.",
                    ], 409);
                }

                $createdAssignments[] = \App\Models\TeacherSubject::create([
                    'teacher_id' => $teacherId,
                    'subject_id' => $data['subject_id'],
                    'section_id' => $data['section_id'] ?? null,
                    'academic_year_id' => $academicYearId,
                    'quarter_id' => $data['quarter_id'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subjects successfully assigned to teacher.',
                'data' => $createdAssignments
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning subjects to teacher: ' . $e->getMessage(), [
                'teacher_id' => $request->input('teacher_id'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while assigning subjects.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getSubjects()
    {
        try {
            $subjects = \App\Models\Subject::select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $subjects
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subjects.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getSectionsByAcademicYear($academicYearId)
    {
        try {
            $sections = \App\Models\Section::where('academic_year_id', $academicYearId)
                ->select('id', 'name', 'academic_year_id')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $sections
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sections.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getAvailableSections(Request $request, $academicYearId)
    {
        $teacherId = $request->query('teacher_id'); // optional param

        $sections = Section::where(function ($query) use ($academicYearId, $teacherId) {
            // Sections without adviser
            $query->whereDoesntHave('sectionAdvisors', function ($sub) use ($academicYearId) {
                $sub->where('academic_year_id', $academicYearId);
            });

            // OR the section already assigned to this teacher
            if ($teacherId) {
                $query->orWhereHas('sectionAdvisors', function ($sub) use ($academicYearId, $teacherId) {
                    $sub->where('academic_year_id', $academicYearId)
                        ->where('teacher_id', $teacherId);
                });
            }
        })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sections
        ]);
    }

    public function getAvailableSubjectsForAssignment($teacherId, $academicYearId)
    {
        try {
            // Subjects already assigned to this teacher for the year
            $assigned = TeacherSubject::where('teacher_id', $teacherId)
                ->where('academic_year_id', $academicYearId)
                ->pluck('subject_id');

            // Only show subjects NOT assigned yet
            $subjects = Subject::whereNotIn('id', $assigned)
                ->select('id', 'name')
                ->orderBy('name', 'asc')
                ->get()
                ->unique('name')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $subjects,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available subjects.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }


    public function getAssignedSubjectsOnly($teacherId, $academicYearId)
    {
        try {
            // Get only subjects the teacher already has
            $subjects = \App\Models\TeacherSubject::with('subject:id,name')
                ->where('teacher_id', $teacherId)
                ->where('academic_year_id', $academicYearId)
                ->get()
                ->pluck('subject')
                ->unique('name') // remove duplicates by name
                ->values();

            return response()->json([
                'success' => true,
                'data' => $subjects,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch assigned subjects.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }



    public function getTeacherDetails($id)
    {
        $teacher = Teacher::with([
            'user:id,email',
            'sectionAdvisors.section:id,name',
            'sectionAdvisors.academicYear:id,name,is_current',
            'teacherSubjects.subject:id,name,code',
            'teacherSubjects.section:id,name',
            'teacherSubjects.academicYear:id,name',
            'schedules.section:id,name',
        ])->find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $teacher
        ]);
    }

    public function getAssignedSubjects($teacherId, $academicYearId)
    {
        try {
            $subjects = \App\Models\TeacherSubject::with('subject:id,name')
                ->where('teacher_id', $teacherId)
                ->where('academic_year_id', $academicYearId)
                ->get()
                ->pluck('subject');

            return response()->json([
                'success' => true,
                'data' => $subjects
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch assigned subjects.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function removeTeacherSectionAdviser($teacherId, $academicYearId)
    {
        try {
            $teacher = Teacher::findOrFail($teacherId);
            $academicYear = AcademicYear::findOrFail($academicYearId);

            $advisor = SectionAdvisor::where('teacher_id', $teacherId)
                ->where('academic_year_id', $academicYearId)
                ->first();

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'No adviser record found for this teacher in the selected academic year.',
                ], 404);
            }

            $advisor->delete();

            return response()->json([
                'success' => true,
                'message' => "Adviser record for {$teacher->first_name} {$teacher->last_name} removed successfully.",
            ], 200);
        } catch (\Throwable $th) {
            Log::error('Error removing teacher adviser', [
                'error' => $th->getMessage(),
                'teacher_id' => $teacherId,
                'academic_year_id' => $academicYearId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }
}
