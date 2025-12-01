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
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

    /**
     * Export SF3 Excel - Textbook Inventory Report
     */
    public function exportSF3Excel(Request $request)
    {
        try {
            $user = Auth::user();
            $teacher = $user->teacher;

            if (!$teacher) {
                return response()->json(['error' => 'Teacher profile not found'], 404);
            }

            $today = Carbon::today();

            // Get all books with their borrowing statistics
            $books = BookInventory::with(['teacher', 'subject'])
                ->withCount([
                    'studentBorrowBooks as total_borrowed' => function ($query) {
                        $query->where('status', '!=', 'returned');
                    },
                    'studentBorrowBooks as overdue_count' => function ($query) use ($today) {
                        $query->where('status', '!=', 'returned')
                            ->whereDate('due_date', '<', $today);
                    }
                ])
                ->orderBy('title')
                ->get();

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SF3 Textbook Inventory');

            // Calculate column count
            $lastColIndex = 7; // No., Title, Author, Category, Total Copies, Available, Issued
            $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(6);  // No.
            $sheet->getColumnDimension('B')->setWidth(35); // Title
            $sheet->getColumnDimension('C')->setWidth(20); // Author
            $sheet->getColumnDimension('D')->setWidth(15); // Category
            $sheet->getColumnDimension('E')->setWidth(12); // Total Copies
            $sheet->getColumnDimension('F')->setWidth(12); // Available
            $sheet->getColumnDimension('G')->setWidth(12); // Issued

            // Header Section
            $row = 1;
            $sheet->setCellValue('A' . $row, 'School Form 3 (SF 3) Textbook Inventory Report');
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $sheet->setCellValue('A' . $row, '(For All Grade Levels)');
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row += 2;
            // School Information
            $sheet->setCellValue('A' . $row, 'School ID:');
            $sheet->setCellValue('B' . $row, '308041');
            $sheet->setCellValue('D' . $row, 'Region:');
            $sheet->setCellValue('E' . $row, 'IV-A');

            $row++;
            $sheet->setCellValue('A' . $row, 'School Name:');
            $sheet->setCellValue('B' . $row, 'Castañas National Highschool');
            $sheet->setCellValue('D' . $row, 'Division:');
            $sheet->setCellValue('E' . $row, 'Quezon Province');

            $row++;
            $sheet->setCellValue('A' . $row, 'District:');
            $sheet->setCellValue('B' . $row, 'Sariaya East');
            $sheet->setCellValue('D' . $row, 'School Year:');
            $sheet->setCellValue('E' . $row, Carbon::now()->format('Y') . '-' . (Carbon::now()->year + 1));

            $row++;
            $sheet->setCellValue('A' . $row, 'Date Prepared:');
            $sheet->setCellValue('B' . $row, Carbon::now()->format('F d, Y'));

            $row += 2;

            // Table Header
            $headerRow = $row;
            $sheet->setCellValue('A' . $row, 'No.');
            $sheet->setCellValue('B' . $row, 'Title');
            $sheet->setCellValue('C' . $row, 'Author');
            $sheet->setCellValue('D' . $row, 'Category');
            $sheet->setCellValue('E' . $row, 'Total Copies');
            $sheet->setCellValue('F' . $row, 'Available');
            $sheet->setCellValue('G' . $row, 'Issued');

            // Apply header styling
            $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

            $row++;

            // Book data rows
            $no = 1;
            $totalCopies = 0;
            $totalAvailable = 0;
            $totalIssued = 0;

            foreach ($books as $book) {
                $issued = ($book->total_copies ?? 0) - ($book->available_quantity ?? 0);
                $available = $book->available_quantity ?? 0;
                $copies = $book->total_copies ?? 0;

                $sheet->setCellValue('A' . $row, $no);
                $sheet->setCellValue('B' . $row, $book->title ?? '');
                $sheet->setCellValue('C' . $row, $book->author ?? '');
                $sheet->setCellValue('D' . $row, $book->category ?? '');
                $sheet->setCellValue('E' . $row, $copies);
                $sheet->setCellValue('F' . $row, $available);
                $sheet->setCellValue('G' . $row, $issued);

                // Center align number columns
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $row . ':G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $totalCopies += $copies;
                $totalAvailable += $available;
                $totalIssued += $issued;

                $no++;
                $row++;
            }

            // Summary row
            $summaryRow = $row;
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->mergeCells('A' . $row . ':D' . $row);
            $sheet->setCellValue('E' . $row, $totalCopies);
            $sheet->setCellValue('F' . $row, $totalAvailable);
            $sheet->setCellValue('G' . $row, $totalIssued);

            // Apply summary styling
            $summaryRange = 'A' . $summaryRow . ':' . $lastCol . $summaryRow;
            $sheet->getStyle($summaryRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E7E6E6']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);
            $sheet->getStyle('A' . $summaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('E' . $summaryRow . ':G' . $summaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Apply borders to data area
            $dataRange = 'A' . $headerRow . ':' . $lastCol . $summaryRow;
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

            // Footer - Guidelines
            $row += 3;
            $sheet->setCellValue('A' . $row, 'GUIDELINES:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
            $row++;
            $sheet->setCellValue('A' . $row, '1. This form shall be accomplished at the beginning and end of each school year.');
            $row++;
            $sheet->setCellValue('A' . $row, '2. Total Copies = Available + Issued');
            $row++;
            $sheet->setCellValue('A' . $row, '3. All textbooks should be accounted for and properly documented.');

            // Signatures
            $row += 3;
            $sheet->setCellValue('A' . $row, 'PREPARED BY:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row += 2;
            $sheet->setCellValue('A' . $row, '(Signature of Librarian/Teacher over Printed Name)');
            $row += 3;
            $sheet->setCellValue('A' . $row, 'CERTIFIED CORRECT & SUBMITTED:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row += 2;
            $sheet->setCellValue('A' . $row, '(Signature of School Head over Printed Name)');

            // Generate Excel file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'SF3_Textbook_Inventory_' . Carbon::now()->format('Y-m-d') . '.xlsx';

            ob_start();
            $writer->save('php://output');
            $content = ob_get_contents();
            ob_end_clean();

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            Log::error('SF3 Excel Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export SF3 Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
