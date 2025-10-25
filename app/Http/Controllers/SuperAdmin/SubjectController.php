<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubjectController extends Controller
{
    public function index()
    {
        try {
            $subjects = Subject::select(['id', 'name', 'code', 'description', 'units', 'is_active'])
                ->orderBy('code')
                ->paginate(20);

            return response()->json($subjects);
        } catch (\Throwable $th) {
            Log::error('Error fetching subjects', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subjects',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'subjects' => 'required|array|min:1',
                'subjects.*.name' => 'required|string|max:255',
                'subjects.*.code' => 'required|string|max:255|unique:subjects,code',
                'subjects.*.description' => 'nullable|string',
                'subjects.*.units' => 'required|integer|min:1|max:10',
                'subjects.*.is_active' => 'boolean',
            ]);

            $createdSubjects = [];

            foreach ($validated['subjects'] as $subjectData) {
                $subject = Subject::create([
                    'name' => $subjectData['name'],
                    'code' => strtoupper($subjectData['code']),
                    'description' => $subjectData['description'] ?? null,
                    'units' => $subjectData['units'],
                    'is_active' => $subjectData['is_active'] ?? true,
                ]);

                $createdSubjects[] = $subject;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdSubjects) . ' subject(s) created successfully',
                'data' => $createdSubjects,
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error creating subjects', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $subject = Subject::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:subjects,code,' . $id,
                'description' => 'nullable|string',
                'units' => 'required|integer|min:1|max:10',
                'is_active' => 'boolean',
            ]);

            $subject->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'] ?? null,
                'units' => $validated['units'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subject updated successfully',
                'data' => $subject,
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error updating subject', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $subject = Subject::findOrFail($id);
            $subject->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subject deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error deleting subject', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function toggleActive(string $id)
    {
        DB::beginTransaction();

        try {
            $subject = Subject::findOrFail($id);
            $subject->is_active = !$subject->is_active;
            $subject->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subject status updated successfully',
                'data' => $subject,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error toggling subject status', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
