<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\SectionAdvisor;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use PhpParser\Node\Expr\FuncCall;

class TeacherController extends Controller
{

    public function index()
    {
        $teacher = Teacher::with(['user:id,email'])
            ->select(['id', 'user_id', 'employee_id', 'first_name', 'middle_name', 'last_name', 'gender', 'address', 'phone', 'specialization', 'hired_date', 'employment_status'])
            ->latest()
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $teacher
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
                'gender' => 'required|string|max:50',
                'address' => 'nullable|string|max:255',
                'phone' => 'nullable|numeric|digits:11',
                'hired_date' => 'nullable|string|max:255',
                'specialization' => 'nullable|string',
            ]);

            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'teacher',
                'is_verified' => true
            ]);

            $teacher = Teacher::create([
                'user_id' => $user->id,
                'employee_id' => strtoupper('TEACHER-' . str_pad(Teacher::max('id') + 1, 4, '0', STR_PAD_LEFT)),
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

            DB::commit();

            return response()->json([
                'message' => 'Teacher registered successfully',
                'teacher' => $teacher,
                'user' => $user
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Server error',
                'error' => $th->getMessage()
            ]);
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
                'gender' => 'required|string|max:50',
                'address' => 'nullable|string|max:255',
                'phone' => 'nullable|numeric|digits:11',
                'hired_date' => 'nullable|string|max:255',
                'specialization' => 'nullable|string',
                'status' => 'required|string|in:active,inactive,terminated'
            ]);

            $user = User::findOrFail($teacher->user_id);

            $user->update([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

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
                'message' => 'Teacher updated successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Server error',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function delete(string $id)
    {
        $teacher = Teacher::findOrFail($id);

        $teacher->delete();

        return response()->json(['message' => 'Teacher deleted successfully']);
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

            foreach ($validated['schedules'] as $scheduleData) {
                // check for conflicts
                $conflict = Schedule::where('academic_year_id', $academicYearId)
                    ->where('day_of_week', $scheduleData['day_of_week'])
                    ->where(function ($query) use ($scheduleData, $teacherId) {
                        $query->where('section_id', $scheduleData['section_id'])
                            ->orWhere('teacher_id', $teacherId);

                        if (!empty($scheduleData['room'])) {
                            $query->orWhere('room', $scheduleData['room']);
                        }
                    })
                    ->where(function ($query) use ($scheduleData) {
                        // Time overlap check
                        $query->whereBetween('start_time', [$scheduleData['start_time'], $scheduleData['end_time']])
                            ->orWhereBetween('end_time', [$scheduleData['start_time'], $scheduleData['end_time']])
                            ->orWhere(function ($q) use ($scheduleData) {
                                $q->where('start_time', '<', $scheduleData['start_time'])
                                    ->where('end_time', '>', $scheduleData['end_time']);
                            });
                    })
                    ->exists();

                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'message' => "Conflict detected for {$scheduleData['day_of_week']} {$scheduleData['start_time']}–{$scheduleData['end_time']} (Teacher/Section/Room overlap)."
                    ], 409);
                }

                // Create schedule if no conflict
                $createdSchedules[] = Schedule::create([
                    'teacher_id' => $teacherId,
                    'subject_id' => $scheduleData['subject_id'],
                    'section_id' => $scheduleData['section_id'],
                    'academic_year_id' => $academicYearId,
                    'quarter_id' => $scheduleData['quarter_id'],
                    'day_of_week' => $scheduleData['day_of_week'],
                    'start_time' => $scheduleData['start_time'],
                    'end_time' => $scheduleData['end_time'],
                    'room' => $scheduleData['room'] ?? null,
                    'is_active' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Schedules created successfully.',
                'schedules' => $createdSchedules,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Server error',
                'error' => $th->getMessage()
            ]);
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

            // Check if the section already has an adviser for the given academic year
            $existingAdviser = SectionAdvisor::where('section_id', $validated['section_id'])
                ->where('academic_year_id', $validated['academic_year_id'])
                ->first();

            if ($existingAdviser) {
                return response()->json([
                    'success' => false,
                    'message' => 'This section already has an adviser assigned for the given academic year.',
                ], 409);
            }

            // Assign new adviser
            $data = SectionAdvisor::create([
                'section_id' => $validated['section_id'],
                'teacher_id' => $validated['teacher_id'],
                'academic_year_id' => $validated['academic_year_id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Teacher successfully assigned as adviser to section.',
                'data' => $data
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error on assigning teacher as an adviser.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
