<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use App\Models\Fee;
use App\Models\User;
use App\Models\StudentPaymentTerm;
use App\Enums\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Services\AccountService;
use App\Services\WorkflowService;
use App\Services\StudentPaymentService;
use App\Models\Workflow;
use App\Events\PaymentRecorded;

class TransactionController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $request->user();

        if (in_array($user->role->value, ['super_admin', 'admin', 'accounting'])) {
            $transactions = Transaction::with('user')
                ->orderByDesc('year')
                ->orderByDesc('semester')
                ->get()
                ->groupBy(fn($txn) => $this->getTransactionGroupKey($txn));
        } else {
            $transactions = $user->transactions()
                ->with('user')
                ->orderByDesc('year')
                ->orderByDesc('semester')
                ->get()
                ->groupBy(fn($txn) => $this->getTransactionGroupKey($txn));
        }

        return Inertia::render('Transactions/Index', [
            'auth'               => ['user' => $user],
            'transactionsByTerm' => $transactions,
            'account'            => $user->account,
            'currentTerm'        => $this->getCurrentTerm(),
        ]);
    }

    // ─── create / store ───────────────────────────────────────────────────────

    public function create()
    {
        $users = User::select('id', 'first_name', 'last_name', 'middle_initial', 'email')->get();

        return Inertia::render('Transactions/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        if (!in_array($request->user()->role->value, ['super_admin', 'admin', 'accounting'])) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'amount'          => 'required|numeric|min:0.01',
            'type'            => 'required|in:charge,payment',
            'payment_channel' => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'user_id'         => $data['user_id'],
            'reference'       => 'SYS-' . Str::upper(Str::random(8)),
            'kind'            => $data['type'],
            'type'            => 'Manual Entry',
            'amount'          => $data['amount'],
            'status'          => $data['type'] === 'payment' ? 'paid' : 'pending',
            'payment_channel' => $data['payment_channel'] ?? null,
            'year'            => (string) now()->year,
            'semester'        => $this->getCurrentSemesterLabel(),
            'meta'            => [
                'description' => 'Manual entry by ' . $request->user()->name,
            ],
        ]);

        $this->recalculateAccount($transaction->user);

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction created successfully!');
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load('user'),
            'account'     => $transaction->user->account,
        ]);
    }

    // ─── receipt (single-transaction PDF) ────────────────────────────────────

    /**
     * Generate a single-payment receipt PDF for one specific transaction.
     *
     * This is what the "📄 Receipt" button on each payment row downloads.
     * It shows only that one payment — what it was for (term name), amount,
     * payment method, date, and the student's account balance.
     *
     * Security:
     *   - Students may only download receipts for their OWN transactions.
     *   - Staff (admin/accounting/super_admin) may download any student's receipt.
     *   - Only 'payment' kind transactions produce receipts.
     */
    public function receipt(Request $request, Transaction $transaction)
    {
        $authUser = $request->user();
        $isStaff  = in_array($authUser->role->value, ['super_admin', 'admin', 'accounting']);

        // Guard: students can only download their own receipt
        if (!$isStaff && $transaction->user_id !== $authUser->id) {
            abort(403, 'You do not have permission to view this receipt.');
        }

        // Guard: receipts are only for payment transactions
        if ($transaction->kind !== 'payment') {
            abort(400, 'Receipts are only available for payment transactions.');
        }

        $targetUser = $transaction->user->load('account', 'student');

        // Compute balance context for the receipt.
        // current balance already reflects this payment if status === 'paid'.
        $currentBalance = (float) ($targetUser->account->balance ?? 0);
        $paymentAmount  = (float) $transaction->amount;

        if ($transaction->status === 'paid') {
            // Payment already reduced the balance — reverse it to show "before"
            $balanceBefore    = round($currentBalance + $paymentAmount, 2);
            $remainingBalance = round($currentBalance, 2);
        } else {
            // Payment not yet applied (awaiting_approval) — balance unchanged
            $balanceBefore    = round($currentBalance, 2);
            $remainingBalance = round($currentBalance - $paymentAmount, 2);
        }

        $pdf = Pdf::loadView('pdf.receipt', [
            'transaction'      => $transaction,
            'student'          => $targetUser,
            'balanceBefore'    => $balanceBefore,
            'remainingBalance' => $remainingBalance,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $studentId = $targetUser->account_id ?? 'unknown';
        $ref       = str_replace(['/', ' '], '-', $transaction->reference ?? (string) $transaction->id);
        $filename  = "receipt-{$studentId}-{$ref}.pdf";

        return $pdf->download($filename);
    }

    // ─── download (full-term summary PDF) ────────────────────────────────────

    /**
     * Generate a full-term transaction summary PDF.
     *
     * Used by the term-group header "📄 Receipt" button — shows ALL transactions
     * for a given academic term (charges + payments) with balance totals.
     * Useful for end-of-term review or for staff auditing.
     *
     * For a single-payment receipt, use receipt() instead.
     *
     * Security: Students always get their own data.
     *           Staff may pass ?user_id=X to view another student's term summary.
     */
    public function download(Request $request)
    {
        $authUser = $request->user();
        $isStaff  = in_array($authUser->role->value, ['super_admin', 'admin', 'accounting']);

        if ($isStaff && $request->filled('user_id')) {
            $targetUser = User::with('account', 'student')->findOrFail((int) $request->user_id);
        } else {
            $targetUser = $authUser->load('account', 'student');
        }

        $query = Transaction::where('user_id', $targetUser->id)
            ->with('fee')
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc');

        $termKey = $request->input('term');
        if ($termKey && $termKey !== 'All Terms') {
            $parts    = explode(' ', $termKey, 2);
            $termYear = $parts[0] ?? null;
            $termSem  = $parts[1] ?? null;

            if ($termYear && $termSem) {
                $query->where('year', $termYear)
                      ->where('semester', $termSem);
            }
        }

        $transactions = $query->get();

        $totalCharges = $transactions->where('kind', 'charge')->sum('amount');
        $totalPaid    = $transactions->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $netBalance   = round((float) $totalCharges - (float) $totalPaid, 2);

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions,
            'student'      => $targetUser,
            'termKey'      => $termKey ?: 'All Terms',
            'totalCharges' => $totalCharges,
            'totalPaid'    => $totalPaid,
            'netBalance'   => $netBalance,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $accountId = $targetUser->account_id ?? 'unknown';
        $termSlug  = $termKey ? str_replace([' ', '/'], '-', $termKey) : 'all-terms';
        $filename  = "transactions-{$accountId}-{$termSlug}.pdf";

        return $pdf->download($filename);
    }

    // ─── payNow ────────────────────────────────────────────────────────────────
    /**
     * Process a payment submission from a student or staff.
     * Students' payments require approval workflow; staff bypass it.
     */
    public function payNow(Request $request)
    {
        $user = $request->user();

        // Determine allowed payment methods based on user role
        // Students cannot use 'cash' - only admin and accounting can record cash payments
        $isStudent = $user->role === \App\Enums\UserRoleEnum::STUDENT;

        if ($isStudent) {
            $allowedMethods = ['gcash', 'bank_transfer', 'credit_card', 'debit_card'];
        } else {
            $allowedMethods = ['cash', 'gcash', 'bank_transfer', 'credit_card', 'debit_card'];
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', 'string', \Illuminate\Validation\Rule::in($allowedMethods)],
            'paid_at' => 'required|date',
            'description' => 'nullable|string|max:255',
            'selected_term_id' => 'required|exists:student_payment_terms,id',
        ]);

        try {
            $paymentService = new \App\Services\StudentPaymentService();

            // Students require approval; staff (admin/accounting) bypass it
            $requiresApproval = $isStudent;

            $result = $paymentService->processPayment($user, (float) $data['amount'], [
                'payment_method'   => $data['payment_method'],
                'paid_at'          => $data['paid_at'],
                'description'      => $data['description'] ?? null,
                'selected_term_id' => (int) $data['selected_term_id'],
                'term_name'        => \App\Models\StudentPaymentTerm::find($data['selected_term_id'])?->term_name,
            ], $requiresApproval);

            // Trigger payment recorded event for notifications (for verified payments only)
            if (!$requiresApproval) {
                event(new \App\Events\PaymentRecorded(
                    $user,
                    $result['transaction_id'] ?? null,
                    (float) $data['amount'],
                    $result['transaction_reference'] ?? 'N/A'
                ));
            }

            // ✅ Only check promotion if user has a student profile and payment is approved
            if ($isStudent && $user->student && !$requiresApproval) {
                $this->checkAndPromoteStudent($user->student);
            }

            $message = $requiresApproval
                ? 'Payment submitted successfully. Please wait for accounting approval.'
                : 'Payment recorded successfully!';

            // Return Inertia-compatible redirect with flash data
            return back()->with([
                'success' => $message,
                'flash' => [
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'requires_approval' => $requiresApproval,
                ],
            ]);
        } catch (\Exception $e) {
            // Return with error message using Inertia session flash
            return back()->withErrors(['payment' => 'Payment processing failed: ' . $e->getMessage()]);
        }
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getTransactionGroupKey(Transaction $txn): string
    {
        if (!empty($txn->year) && !empty($txn->semester)) {
            return "{$txn->year} {$txn->semester}";
        }

        if (empty($txn->year) && empty($txn->semester)) {
            return $this->getCurrentTerm();
        }

        $label = trim("{$txn->year} {$txn->semester}");
        return $label ?: $this->getCurrentTerm();
    }

    private function getCurrentTerm(): string
    {
        return now()->year . ' ' . $this->getCurrentSemesterLabel();
    }

    private function getCurrentSemesterLabel(): string
    {
        $month = now()->month;
        return ($month >= 6 && $month <= 10) ? '1st Sem' : '2nd Sem';
    }

    protected function recalculateAccount(User $user): void
    {
        $charges  = $user->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $user->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $balance  = round((float) $charges - (float) $payments, 2);

        $account = $user->account ?? $user->account()->create(['balance' => 0]);
        $account->update(['balance' => $balance]);
    }

    protected function checkAndPromoteStudent($student): void
    {
        if (!$student) return;
        $user = $student->user;
        if (!$user) return;
        if ($user->account && $user->account->balance <= 0) {
            $this->promoteYearLevel($student);
            $this->assignNextPayables($student);
        }
    }

    protected function promoteYearLevel($student): void
    {
        $levels       = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $currentIndex = array_search($student->year_level, $levels);
        if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
            $student->year_level = $levels[$currentIndex + 1];
            $student->save();
        }
    }

    protected function assignNextPayables($student): void
    {
        $fees = Fee::where('year_level', $student->year_level)
            ->where('semester', '1st Sem')
            ->get();

        foreach ($fees as $fee) {
            $student->user->transactions()->create([
                'reference' => 'FEE-' . strtoupper($fee->name) . '-' . $student->id,
                'kind'      => 'charge',
                'type'      => $fee->name,
                'amount'    => $fee->amount,
                'status'    => 'pending',
                'year'      => (string) now()->year,
                'semester'  => $this->getCurrentSemesterLabel(),
                'meta'      => ['description' => $fee->name],
            ]);
        }
    }
}