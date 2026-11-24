<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookLoan;
use App\Support\Audit;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryController extends Controller
{
    private const MAX_ACTIVE_LOANS = 5;

    public static function overdueDays(BookLoan $loan): int
    {
        if ($loan->status !== 'borrowed') {
            return 0;
        }

        return max(0, Carbon::today()->diffInDays($loan->due_on, false) * -1);
    }

    public function loans(Request $request): JsonResponse
    {
        $q = BookLoan::with(['book:id,title,author', 'student:id,admission_no,first_name,last_name', 'staff:id,staff_no,first_name,last_name'])->orderByDesc('id');

        if (($status = $request->query('status')) === 'overdue') {
            $q->where('status', 'borrowed')->where('due_on', '<', Carbon::today()->toDateString());
        } elseif ($status) {
            $q->where('status', $status);
        }
        foreach (['book_id', 'student_id', 'staff_id', 'borrower_type'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if (($s = trim((string) $request->query('search', ''))) !== '') {
            $q->where(fn ($w) => $w->whereHas('book', fn ($b) => $b->where('title', 'like', "%$s%"))
                ->orWhereHas('student', fn ($b) => $b->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhere('admission_no', 'like', "%$s%"))
                ->orWhereHas('staff', fn ($b) => $b->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")));
        }

        $perDay = Settings::float('library_fine_per_day', 0);
        $page = $q->paginate(min((int) $request->query('per_page', 15), 100));
        $page->through(function (BookLoan $l) use ($perDay) {
            $days = self::overdueDays($l);
            $l->setAttribute('overdue_days', $days);
            $l->setAttribute('fine_due', $l->status === 'borrowed' ? round($days * $perDay, 2) : (float) $l->fine);

            return $l;
        });

        return response()->json($page);
    }

    public function borrow(Request $request): JsonResponse
    {
        $d = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'borrower_type' => ['required', 'in:student,staff'],
            'student_id' => ['required_if:borrower_type,student', 'nullable', 'integer', 'exists:students,id'],
            'staff_id' => ['required_if:borrower_type,staff', 'nullable', 'integer', 'exists:staff,id'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $loan = DB::transaction(function () use ($d, $request) {
            $book = Book::whereKey($d['book_id'])->lockForUpdate()->firstOrFail();
            if ($book->copies_available < 1) {
                throw ValidationException::withMessages(['book_id' => 'No copies of this book are available.']);
            }

            $borrowerCol = $d['borrower_type'] === 'student' ? 'student_id' : 'staff_id';
            $active = BookLoan::where($borrowerCol, $d[$borrowerCol])->where('status', 'borrowed');
            if ((clone $active)->count() >= self::MAX_ACTIVE_LOANS) {
                throw ValidationException::withMessages([$borrowerCol => 'This borrower already has '.self::MAX_ACTIVE_LOANS.' books out.']);
            }
            if ((clone $active)->where('book_id', $book->id)->exists()) {
                throw ValidationException::withMessages(['book_id' => 'This borrower already has a copy of this book.']);
            }
            if ((clone $active)->where('due_on', '<', Carbon::today()->toDateString())->exists()) {
                throw ValidationException::withMessages([$borrowerCol => 'This borrower has overdue books and must return them first.']);
            }

            $book->decrement('copies_available');

            return BookLoan::create([
                'book_id' => $book->id, 'borrower_type' => $d['borrower_type'],
                'student_id' => $d['borrower_type'] === 'student' ? $d['student_id'] : null,
                'staff_id' => $d['borrower_type'] === 'staff' ? $d['staff_id'] : null,
                'issued_on' => now()->toDateString(),
                'due_on' => $d['due_on'] ?? now()->addDays((int) Settings::get('library_loan_days', 14))->toDateString(),
                'status' => 'borrowed', 'issued_by' => $request->user()->id, 'notes' => $d['notes'] ?? null,
            ]);
        });
        Audit::log('library.borrowed', $loan);

        return response()->json($loan->load(['book:id,title', 'student:id,first_name,last_name', 'staff:id,first_name,last_name']), 201);
    }

    public function returnBook(Request $request, BookLoan $loan): JsonResponse
    {
        abort_if($loan->status !== 'borrowed', 422, 'This loan is already closed.');

        DB::transaction(function () use ($loan) {
            $days = self::overdueDays($loan);
            $loan->update(['status' => 'returned', 'returned_on' => now()->toDateString(), 'fine' => round($days * Settings::float('library_fine_per_day', 0), 2)]);
            Book::whereKey($loan->book_id)->lockForUpdate()->increment('copies_available');
        });
        Audit::log('library.returned', $loan);

        return response()->json($loan->fresh());
    }

    public function markLost(Request $request, BookLoan $loan): JsonResponse
    {
        abort_if($loan->status !== 'borrowed', 422, 'This loan is already closed.');

        DB::transaction(function () use ($loan) {
            $loan->update(['status' => 'lost', 'returned_on' => now()->toDateString()]);
            Book::whereKey($loan->book_id)->lockForUpdate()->decrement('copies_total'); // the copy leaves the inventory
        });
        Audit::log('library.lost', $loan);

        return response()->json($loan->fresh());
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'titles' => Book::count(),
            'copies' => (int) Book::sum('copies_total'),
            'available' => (int) Book::sum('copies_available'),
            'on_loan' => BookLoan::where('status', 'borrowed')->count(),
            'overdue' => BookLoan::where('status', 'borrowed')->where('due_on', '<', Carbon::today()->toDateString())->count(),
        ]);
    }
}
