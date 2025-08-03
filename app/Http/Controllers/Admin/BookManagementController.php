<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookInventory;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentBorrowBook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $today = Carbon::today();

        $books = BookInventory::with(['teacher', 'subject'])
            ->withCount([
                'studentBorrowBooks as overdue_count' => function ($query) use ($today) {
                    $query->where('status', '!=', 'returned')
                        ->whereDate('expected_return_date', '<', $today);
                }
            ])
            ->latest()
            ->paginate(25);

        return response()->json([
            'books' => $books
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();
            $teacher = $user->teacher;

            if (!$teacher) {
                return response()->json(['error' => 'Teacher profile not found'], 404);
            }

            $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'subject_id' => ['required', 'exists:subjects,id'],
                'total_copies' => ['required', 'numeric'],
                'available' => ['required', 'numeric'],
            ]);

            BookInventory::create([
                'title' => $request->input('title'),
                'teacher_id' => $teacher->id,
                'subject_id' => $request->input('subject_id'),
                'total_copies' => $request->input('total_copies'),
                'available' => $request->input('available'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully add new book to inventory'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add new book to inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $book = BookInventory::findOrFail($id);
            $borrowRecords = $book->studentBorrowBooks()->with('student')->paginate(25);

            return response()->json([
                'success' => true,
                'book' => $book,
                'bookInfo' => $borrowRecords
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch book info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();
            $teacher = $user->teacher;

            if (!$teacher) {
                return response()->json(['error' => 'Teacher profile not found'], 404);
            }

            $book = BookInventory::findOrFail($id);

            $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'subject_id' => ['required', 'exists:subjects,id'],
                'total_copies' => ['required', 'numeric'],
                'available' => ['required', 'numeric'],
            ]);

            $book->update([
                'title' => $request->input('title'),
                'teacher_id' => $teacher->id,
                'subject_id' => $request->input('subject_id'),
                'total_copies' => $request->input('total_copies'),
                'available' => $request->input('available'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update book information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $book = BookInventory::findOrFail($id);
            $book->delete();

            return response()->json([
                'success' => true,
                'message' => 'Book deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete book',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getFilterOptions(Request $request)
    {
        $sections = Section::all(['id', 'name']);

        // Optional: only fetch if section_id is provided
        $students = [];
        if ($request->has('section_id')) {
            $students = Student::where('section_id', $request->section_id)
                ->select('id', 'first_name', 'middle_name', 'last_name')
                ->get()
                ->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'full_name' => $student->fullName()
                    ];
                })
                ->values();
        }

        $availableBooks = BookInventory::where('available', '>', 0)
            ->select('id', 'title', 'available')
            ->get();

        return response()->json([
            'sections' => $sections,
            'students' => $students,
            'books' => $availableBooks,
        ]);
    }


    public function distributeBooks(Request $request)
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'book_id' => ['required', 'exists:book_inventories,id'],
            'issued_date' => ['required', 'date'],
            'expected_return_date' => ['required', 'date'],
            'status' => ['required', 'in:issued,returned,overdue'],
        ]);

        DB::beginTransaction();

        try {
            $book = BookInventory::findOrFail($request->book_id);

            if ($request->status === 'issued' && $book->available < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available copies to distribute.',
                ], 400);
            }

            StudentBorrowBook::create([
                'student_id' => $request->student_id,
                'book_id' => $book->id,
                'issued_date' => $request->issued_date,
                'expected_return_date' => $request->expected_return_date,
                'status' => $request->status
            ]);

            // Decrease available stock if book is being issued
            if ($request->status === 'issued') {
                $book->decrement('available');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book successfully distributed to the student.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to distribute book',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function returnBook(string $id)
    {
        DB::beginTransaction();
        try {
            $studentBorrowBook = StudentBorrowBook::findOrFail($id);

            if ($studentBorrowBook->status === 'returned') {
                return response()->json([
                    'success' => false,
                    'message' => 'This book has already been returned.'
                ], 400);
            }

            $studentBorrowBook->update([
                'returned_date' => now(),
                'status' => 'returned'
            ]);

            $book = BookInventory::findOrFail($studentBorrowBook->book_id);
            $book->increment('available');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully returned the issued book'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update issued book',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
