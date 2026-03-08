<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use App\Models\Fee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Services\AccountService;
use App\Services\WorkflowService;
use App\Models\Workflow;

class TransactionController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

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

    private function getTransactionGroupKey($txn): string
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
        $month = now()->month;
        $year  = now()->year;

        if ($month >= 6 && $month <= 10) {
            $schoolYearStart = $year;
            $semester        = '1st Sem';
        } elseif ($month >= 11) {
            $schoolYearStart = $year;
            $semester        = '2nd Sem';
        } else {
            $schoolYearStart = $year - 1;
            $semester        = '2nd Sem';
        }

        return "{$schoolYearStart} {$semester}";
    }

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
            'user_id'        => 'required|exists:users,id',
            'amount'         => 'required|numeric|min:0.01',
            'type'           => 'required|in:charge,payment',
            'payment_channel'=> 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'user_id'         => $data['user_id'],
            'reference'       => 'SYS-' . Str::upper(Str::random(8)),
            'kind'            => $data['type'],
            'type'            => 'Manual Entry',
            'amount'          => $data['amount'],
            'status'          => $data['type'] === 'payment' ? 'paid' : 'pending',
            'payment_channel' => $data['payment_channel'] ?? null,
            'year'            => now()->year,
            'semester'        => $this->getCurrentTerm(),
        ]);

        $this->recalculateAccount($transaction->user);

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction created successfully!');
    }

    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load('user'),
            'account'     => $transaction->user->account,
        ]);
    }

    public function payNow(Request $request)
    {
        $user = $request->user();

        $isStudent = $user->role === \App\Enums\UserRoleEnum::STUDENT;

        if ($isStudent) {
            $allowedMethods = ['gcash', 'bank_transfer', 'credit_card', 'debit_card'];
        } else {
            $allowedMethods = ['cash', 'gcash', 'bank_transfer', 'credit_card', 'debit_card'];
        }

        $data = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'payment_method'   => ['required', 'string', \Illuminate\Validation\Rule::in($allowedMethods)],
            'paid_at'          => 'required|date',
            'description'      => 'nullable|string|max:255',
            'selected_term_id' => 'required|exists:student_payment_terms,id',
        ]);

        try {
            $paymentService = new \App\Services\StudentPaymentService();
            $requiresApproval = $isStudent;

            $result = $paymentService->processPayment($user, (float) $data['amount'], [
                'payment_method'   => $data['payment_method'],
                'paid_at'          => $data['paid_at'],
                'description'      => $data['description'] ?? null,
                'selected_term_id' => (int) $data['selected_term_id'],
                'term_name'        => \App\Models\StudentPaymentTerm::find($data['selected_term_id'])?->term_name,
            ], $requiresApproval);

            if ($requiresApproval) {
                $paymentWorkflow = Workflow::where('type', 'payment_approval')
                    ->where('is_active', true)
                    ->first();

                if ($paymentWorkflow) {
                    $transaction = \App\Models\Transaction::find($result['transaction_id']);
                    try {
                        $this->workflowService->startWorkflow(
                            $paymentWorkflow,
                            $transaction,
                            $user->id
                        );
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to start payment approval workflow', [
                            'transaction_id' => $result['transaction_id'],
                            'error'          => $e->getMessage(),
                        ]);
                    }
                }
            }

            if (!$requiresApproval) {
                event(new \App\Events\PaymentRecorded(
                    $user,
                    $result['transaction_id'],
                    (float) $data['amount'],
                    $result['transaction_reference']
                ));
            }

            if ($isStudent && $user->student && !$requiresApproval) {
                $this->checkAndPromoteStudent($user->student);
            }

            return redirect()->route('student.account', ['tab' => 'history'])
                ->with('success', $result['message']);

        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }
    }

    protected function recalculateAccount($user): void
    {
        $charges  = $user->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $user->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $balance  = $charges - $payments;

        $account = $user->account ?? $user->account()->create();
        $account->update(['balance' => $balance]);
    }

    protected function checkAndPromoteStudent($student)
    {
        if (!$student) return;
        $user    = $student->user;
        if (!$user) return;
        $account = $user->account;
        if ($account && $account->balance <= 0) {
            $this->promoteYearLevel($student);
            $this->assignNextPayables($student);
        }
    }

    protected function promoteYearLevel($student)
    {
        $levels       = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $currentIndex = array_search($student->year_level, $levels);
        if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
            $student->year_level = $levels[$currentIndex + 1];
            $student->save();
        }
    }

    protected function assignNextPayables($student)
    {
        $fees = \App\Models\Fee::where('year_level', $student->year_level)
            ->where('semester', '1st Sem')
            ->get();

        foreach ($fees as $fee) {
            $student->user->transactions()->create([
                'reference' => 'FEE-' . strtoupper($fee->name) . '-' . $student->id,
                'kind'      => 'charge',
                'type'      => $fee->name,
                'amount'    => $fee->amount,
                'status'    => 'pending',
                'meta'      => ['description' => $fee->name],
            ]);
        }
    }

    /**
     * Download a transaction receipt PDF.
     *
     * Scoped by:
     * - user_id  : students only see their own transactions
     * - term     : optional ?term=2026+1st+Sem query parameter to filter by term
     *
     * Staff (admin/accounting) can pass ?user_id=X to generate a receipt for
     * a specific student.
     */
    public function download(Request $request)
    {
        $authUser  = $request->user();
        $isStaff   = in_array($authUser->role->value, ['super_admin', 'admin', 'accounting']);

        // Determine which user's transactions to export
        if ($isStaff && $request->filled('user_id')) {
            $targetUser = User::with('account', 'student')->findOrFail($request->user_id);
        } else {
            $targetUser = $authUser->load('account', 'student');
        }

        // Base query — always scoped to one user
        $query = Transaction::where('user_id', $targetUser->id)
            ->with('fee')
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc');

        // Optional: filter by a specific term key (e.g. "2026 1st Sem")
        $termKey = $request->input('term');
        if ($termKey) {
            // Term key format: "{year} {semester}", e.g. "2026 1st Sem"
            $parts    = explode(' ', $termKey, 2);
            $termYear = $parts[0] ?? null;
            $termSem  = $parts[1] ?? null;

            if ($termYear && $termSem) {
                $query->where('year', $termYear)
                      ->where('semester', $termSem);
            }
        }

        $transactions = $query->get();

        // Calculate summary totals
        $totalCharges  = $transactions->where('kind', 'charge')->sum('amount');
        $totalPaid     = $transactions->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $netBalance    = $totalCharges - $totalPaid;

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions,
            'student'      => $targetUser,
            'termKey'      => $termKey ?: 'All Terms',
            'totalCharges' => $totalCharges,
            'totalPaid'    => $totalPaid,
            'netBalance'   => $netBalance,
        ]);

        $pdf->setPaper('A4', 'portrait');

        // Build a descriptive filename
        $accountId = $targetUser->account_id ?? 'unknown';
        $termSlug  = $termKey ? str_replace([' ', '/'], '-', $termKey) : 'all-terms';
        $filename  = "transactions-{$accountId}-{$termSlug}.pdf";

        return $pdf->download($filename);
    }
}