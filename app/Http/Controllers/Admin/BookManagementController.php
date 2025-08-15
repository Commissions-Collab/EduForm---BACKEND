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
                        ->whereDate('return_date', '<', $today);
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
                'author' => ['required', 'string'],
                'category' => ['required', 'string'],
                'total_copies' => ['required', 'numeric'],
                'available_quantity' => ['required', 'numeric'],
            ]);

            BookInventory::create([
                'title' => $request->input('title'),
                'author' => $request->input('author'),
                'category' => $request->input('category'),
                'total_copies' => $request->input('total_copies'),
                'available_quantity' => $request->input('available_quantity'),
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
                'author' => ['required', 'string'],
                'category' => ['required', 'string'],
                'total_copies' => ['required', 'numeric'],
                'available_quantity' => ['required', 'numeric'],
            ]);

            $book->update([
                'title' => $request->input('title'),
                'author' => $request->input('author'),
                'category' => $request->input('category'),
                'total_copies' => $request->input('total_copies'),
                'available_quantity' => $request->input('available_quantity'),
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

        $students = [];

        if ($request->has('section_id')) {
            $students = Student::whereHas('enrollments', function ($query) use ($request) {
                $query->where('section_id', $request->section_id)
                    ->where('enrollment_status', 'enrolled');

                // Optional: Add academic_year_id filter if needed
                if ($request->has('academic_year_id')) {
                    $query->where('academic_year_id', $request->academic_year_id);
                }
            })
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

        $availableBooks = BookInventory::where('available_quantity', '>', 0)
            ->select('id', 'title', 'available_quantity')
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
            'borrow_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'return_date' => ['required', 'date'],
            'status' => ['required', 'in:issued,returned,overdue'],
        ]);

        DB::beginTransaction();

        try {
            $book = BookInventory::findOrFail($request->book_id);

            if ($request->status === 'issued' && $book->available_quantity < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available copies to distribute.',
                ], 400);
            }

            StudentBorrowBook::create([
                'student_id' => $request->student_id,
                'book_id' => $book->id,
                'borrow_date' => $request->borrow_date,
                'due_date' => $request->due_date,
                'return_date' => $request->return_date,
                'status' => $request->status
            ]);

            // Decrease available stock if book is being issued
            if ($request->status === 'issued') {
                $book->decrement('available_quantity');
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
                'return_date' => now(),
                'status' => 'returned'
            ]);

            $book = BookInventory::findOrFail($studentBorrowBook->book_id);
            $book->increment('available_quantity');

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
