<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BookInventory;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentBorrowBook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookManagementController extends Controller
{
    /**
     * Display all textbooks with statistics
     * Returns flat array instead of paginated for frontend pagination
     */
    public function index()
    {
        try {
            $today = Carbon::today();

            $books = BookInventory::withCount([
                'studentBorrowBooks as overdue_count' => function ($query) use ($today) {
                    $query->where('status', '!=', 'returned')
                        ->whereDate('return_date', '<', $today);
                },
                'studentBorrowBooks as issued_count' => function ($query) {
                    $query->where('status', 'issued');
                }
            ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'books' => $books,
                'total' => $books->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch textbooks: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch textbooks',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new textbook
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'author' => ['nullable', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:255'],
                'total_copies' => ['required', 'integer', 'min:1'],
                'available_quantity' => ['required', 'integer', 'min:0'],
            ]);

            // Validate available quantity doesn't exceed total copies
            if ($validated['available_quantity'] > $validated['total_copies']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Available quantity cannot exceed total copies'
                ], 422);
            }

            $book = BookInventory::create($validated);

            DB::commit();

            Log::info('Textbook created', ['book_id' => $book->id]);

            return response()->json([
                'success' => true,
                'message' => 'Textbook added successfully',
                'book' => $book
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create textbook: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to add textbook',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display specific textbook with borrowing history
     */
    public function show($id)
    {
        try {
            $book = BookInventory::with([
                'studentBorrowBooks' => function ($query) {
                    $query->with('student')->latest();
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'book' => $book,
                'borrowHistory' => $book->studentBorrowBooks
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Textbook not found: ' . $id);
            return response()->json([
                'success' => false,
                'error' => 'Textbook not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch textbook: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch textbook',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update textbook information
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $book = BookInventory::findOrFail($id);

            $validated = $request->validate([
                'title' => ['sometimes', 'string', 'max:255'],
                'author' => ['sometimes', 'nullable', 'string', 'max:255'],
                'category' => ['sometimes', 'nullable', 'string', 'max:255'],
                'total_copies' => ['sometimes', 'integer', 'min:1'],
                'available_quantity' => ['sometimes', 'integer', 'min:0'],
            ]);

            // If updating quantities, validate
            if (isset($validated['total_copies']) || isset($validated['available_quantity'])) {
                $total = $validated['total_copies'] ?? $book->total_copies;
                $available = $validated['available_quantity'] ?? $book->available_quantity;

                if ($available > $total) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => 'Available quantity cannot exceed total copies'
                    ], 422);
                }
            }

            $book->update($validated);

            DB::commit();

            Log::info('Textbook updated', ['book_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Textbook updated successfully',
                'book' => $book
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::warning('Textbook not found for update: ' . $id);
            return response()->json([
                'success' => false,
                'error' => 'Textbook not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update textbook: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to update textbook',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a textbook
     */
    public function destroy($id)
    {
        try {
            $book = BookInventory::findOrFail($id);

            // Check if book has active borrowings
            $activeBorrowings = $book->studentBorrowBooks()
                ->where('status', '!=', 'returned')
                ->count();

            if ($activeBorrowings > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete textbook with active borrowings',
                    'activeCount' => $activeBorrowings
                ], 422);
            }

            $book->delete();

            Log::info('Textbook deleted', ['book_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Textbook deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Textbook not found for deletion: ' . $id);
            return response()->json([
                'success' => false,
                'error' => 'Textbook not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete textbook: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete textbook',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options for book distribution
     */
    public function getFilterOptions(Request $request)
    {
        try {
            $sections = Section::all(['id', 'name']);

            $students = [];
            if ($request->has('section_id')) {
                $students = Student::whereHas('enrollments', function ($query) use ($request) {
                    $query->where('section_id', $request->section_id)
                        ->where('enrollment_status', 'enrolled');

                    if ($request->has('academic_year_id')) {
                        $query->where('academic_year_id', $request->academic_year_id);
                    }
                })
                    ->select('id', 'first_name', 'middle_name', 'last_name')
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get()
                    ->map(function ($student) {
                        return [
                            'id' => $student->id,
                            'full_name' => trim($student->first_name . ' ' . ($student->middle_name ?? '') . ' ' . $student->last_name)
                        ];
                    })
                    ->values();
            }

            $availableBooks = BookInventory::where('available_quantity', '>', 0)
                ->select('id', 'title', 'available_quantity')
                ->orderBy('title')
                ->get();

            return response()->json([
                'success' => true,
                'sections' => $sections,
                'students' => $students,
                'books' => $availableBooks
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch filter options: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch filter options',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Distribute book to student
     */
    public function distributeBooks(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'book_id' => ['required', 'exists:book_inventories,id'],
            'borrow_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after:borrow_date'],
            'return_date' => ['nullable', 'date'],
            'status' => ['required', 'in:issued,returned,overdue'],
        ]);

        DB::beginTransaction();

        try {
            $book = BookInventory::findOrFail($validated['book_id']);

            if ($validated['status'] === 'issued' && $book->available_quantity < 1) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'No available copies to distribute'
                ], 400);
            }

            $borrow = StudentBorrowBook::create($validated);

            if ($validated['status'] === 'issued') {
                $book->decrement('available_quantity');
            }

            DB::commit();

            Log::info('Book distributed', ['book_id' => $validated['book_id'], 'student_id' => $validated['student_id']]);

            return response()->json([
                'success' => true,
                'message' => 'Book distributed successfully',
                'borrow' => $borrow
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to distribute book: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to distribute book',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return a borrowed book
     */
    public function returnBook($id)
    {
        DB::beginTransaction();

        try {
            $borrowRecord = StudentBorrowBook::findOrFail($id);

            if ($borrowRecord->status === 'returned') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'This book has already been returned'
                ], 400);
            }

            $borrowRecord->update([
                'return_date' => now(),
                'status' => 'returned'
            ]);

            $book = BookInventory::findOrFail($borrowRecord->book_id);
            $book->increment('available_quantity');

            DB::commit();

            Log::info('Book returned', ['borrow_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Book returned successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::warning('Borrow record not found: ' . $id);
            return response()->json([
                'success' => false,
                'error' => 'Record not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to return book: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to return book',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
