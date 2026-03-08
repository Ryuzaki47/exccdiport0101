<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Subject;
use App\Models\Fee;
use App\Models\Transaction;
use App\Models\Payment;
use App\Services\StudentPaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentFeeController extends Controller
{
    /**
     * Display listing of students for fee management
     */
    public function index(Request $request)
    {
        $query = User::with(['student', 'account', 'latestAssessment.paymentTerms'])
            ->where('role', 'student');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')) like ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->paginate(15)->withQueryString();

        $courses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course');

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $statuses = [
            User::STATUS_ACTIVE    => 'Active',
            User::STATUS_GRADUATED => 'Graduated',
            User::STATUS_DROPPED   => 'Dropped',
        ];

        return Inertia::render('StudentFees/Index', [
            'students'   => $students,
            'filters'    => $request->only(['search', 'course', 'year_level', 'status']),
            'courses'    => $courses,
            'yearLevels' => $yearLevels,
            'statuses'   => $statuses,
        ]);
    }

    /**
     * Show create assessment form
     */
    public function create(Request $request)
    {
        if ($request->has('get_data') && $request->has('student_id')) {
            $student = User::where('role', 'student')->findOrFail($request->student_id);

            $subjects = Subject::active()
                ->where('course', $student->course)
                ->where('year_level', $student->year_level)
                ->get()
                ->map(fn($subject) => [
                    'id'             => $subject->id,
                    'code'           => $subject->code,
                    'name'           => $subject->name,
                    'units'          => $subject->units,
                    'price_per_unit' => $subject->price_per_unit,
                    'has_lab'        => $subject->has_lab,
                    'lab_fee'        => $subject->lab_fee,
                    'total_cost'     => $subject->total_cost,
                ]);

            $fees = Fee::active()
                ->whereIn('category', ['Laboratory', 'Library', 'Athletic', 'Miscellaneous'])
                ->get()
                ->map(fn($fee) => [
                    'id'       => $fee->id,
                    'name'     => $fee->name,
                    'category' => $fee->category,
                    'amount'   => $fee->amount,
                ]);

            return response()->json([
                'subjects' => $subjects,
                'fees'     => $fees,
            ]);
        }

        $students = User::where('role', 'student')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn($user) => [
                'id'         => $user->id,
                'account_id' => $user->account_id,
                'name'       => $user->name,
                'email'      => $user->email,
                'course'     => $user->course,
                'year_level' => $user->year_level,
                'status'     => $user->status,
            ]);

        $currentYear = now()->year;

        return Inertia::render('StudentFees/Create', [
            'students'    => $students,
            'yearLevels'  => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'semesters'   => ['1st Sem', '2nd Sem', 'Summer'],
            'schoolYears' => [
                "{$currentYear}-" . ($currentYear + 1),
                ($currentYear - 1) . "-{$currentYear}",
            ],
        ]);
    }

    /**
     * Store new assessment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'              => 'required|exists:users,id',
            'year_level'           => 'required|string',
            'semester'             => 'required|string',
            'school_year'          => 'required|string',
            'subjects'             => 'required|array|min:1',
            'subjects.*.id'        => 'required|exists:subjects,id',
            'subjects.*.units'     => 'required|numeric|min:0',
            'subjects.*.amount'    => 'required|numeric|min:0',
            'other_fees'           => 'nullable|array',
            'other_fees.*.id'      => 'required|exists:fees,id',
            'other_fees.*.amount'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $tuitionFee     = collect($validated['subjects'])->sum('amount');
            $otherFeesTotal = isset($validated['other_fees'])
                ? collect($validated['other_fees'])->sum('amount')
                : 0;

            $assessment = StudentAssessment::create([
                'user_id'           => $validated['user_id'],
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level'        => $validated['year_level'],
                'semester'          => $validated['semester'],
                'school_year'       => $validated['school_year'],
                'tuition_fee'       => $tuitionFee,
                'other_fees'        => $otherFeesTotal,
                'total_assessment'  => $tuitionFee + $otherFeesTotal,
                'subjects'          => $validated['subjects'],
                'fee_breakdown'     => $validated['other_fees'] ?? [],
                'created_by'        => auth()->id(),
                'status'            => 'active',
            ]);

            foreach ($validated['subjects'] as $subject) {
                Transaction::create([
                    'user_id'   => $validated['user_id'],
                    'reference' => 'SUBJ-' . strtoupper(Str::random(8)),
                    'kind'      => 'charge',
                    'type'      => 'Tuition',
                    'year'      => explode('-', $validated['school_year'])[0],
                    'semester'  => $validated['semester'],
                    'amount'    => $subject['amount'],
                    'status'    => 'pending',
                    'meta'      => [
                        'assessment_id' => $assessment->id,
                        'subject_id'    => $subject['id'],
                        'description'   => 'Tuition Fee - Subject',
                    ],
                ]);
            }

            if (isset($validated['other_fees'])) {
                foreach ($validated['other_fees'] as $fee) {
                    $feeModel = Fee::find($fee['id']);
                    Transaction::create([
                        'user_id'   => $validated['user_id'],
                        'fee_id'    => $fee['id'],
                        'reference' => 'FEE-' . strtoupper(Str::random(8)),
                        'kind'      => 'charge',
                        'type'      => $feeModel->category,
                        'year'      => explode('-', $validated['school_year'])[0],
                        'semester'  => $validated['semester'],
                        'amount'    => $fee['amount'],
                        'status'    => 'pending',
                        'meta'      => [
                            'assessment_id' => $assessment->id,
                            'fee_code'      => $feeModel->code,
                            'fee_name'      => $feeModel->name,
                        ],
                    ]);
                }
            }

            $user = User::find($validated['user_id']);
            \App\Services\AccountService::recalculate($user);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $validated['user_id'])
                ->with('success', 'Student fee assessment created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create assessment: ' . $e->getMessage()]);
        }
    }

    /**
     * Show student fee details
     */
    public function show($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        // All active assessments — for the semester download selector
        $allAssessments = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('school_year')
            ->orderByRaw("FIELD(semester, '1st Sem', '2nd Sem', 'Summer')")
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'semester'         => $a->semester,
                'school_year'      => $a->school_year,
                'year_level'       => $a->year_level,
                'total_assessment' => (float) $a->total_assessment,
            ]);

        // Latest active assessment WITH paymentTerms eager-loaded as a collection
        $latestAssessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['paymentTerms' => fn($q) => $q->orderBy('term_order')])
            ->latest()
            ->first();

        // Enrich assessment for the Vue component — keep the paymentTerms as an array
        $assessmentData = null;
        if ($latestAssessment) {
            $assessmentData = array_merge(
                $latestAssessment->toArray(),
                [
                    'paymentTerms' => $latestAssessment->paymentTerms
                        ->map(fn($term) => [
                            'id'         => $term->id,
                            'term_name'  => $term->term_name,
                            'term_order' => $term->term_order,
                            'percentage' => $term->percentage,
                            'amount'     => (float) $term->amount,
                            'balance'    => (float) $term->balance,
                            'due_date'   => $term->due_date,
                            'status'     => $term->status,
                            'remarks'    => $term->remarks,
                            'paid_date'  => $term->paid_date,
                        ])
                        ->toArray(),
                ]
            );
        }

        // All transactions for this student
        $transactions = Transaction::where('user_id', $userId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // Payments from the payments table
        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->orderBy('paid_at', 'desc')
            ->get();

        // ── Fee breakdown (preferred source: assessment JSON; fallback: transactions) ──
        if ($latestAssessment) {
            $feeBreakdown = collect();

            if ((float) $latestAssessment->tuition_fee > 0) {
                $feeBreakdown->push([
                    'category' => 'Tuition',
                    'total'    => (float) $latestAssessment->tuition_fee,
                    'items'    => 1,
                ]);
            }

            $storedBreakdown = $latestAssessment->fee_breakdown ?? [];
            if (!empty($storedBreakdown)) {
                $grouped = collect($storedBreakdown)->groupBy('category');
                foreach ($grouped as $category => $items) {
                    $feeBreakdown->push([
                        'category' => $category,
                        'total'    => $items->sum('amount'),
                        'items'    => $items->count(),
                    ]);
                }
            } elseif ((float) $latestAssessment->other_fees > 0) {
                $feeBreakdown->push([
                    'category' => 'Miscellaneous',
                    'total'    => (float) $latestAssessment->other_fees,
                    'items'    => 1,
                ]);
            }
        } else {
            $feeBreakdown = $transactions->where('kind', 'charge')
                ->groupBy('type')
                ->map(fn($group) => [
                    'category' => $group->first()->type,
                    'total'    => $group->sum('amount'),
                    'items'    => $group->count(),
                ]);
        }

        return Inertia::render('StudentFees/Show', [
            'student'          => $student,
            'student_model_id' => $student->student->id ?? null,
            'assessment'       => $assessmentData,
            'allAssessments'   => $allAssessments,
            'transactions'     => $transactions,
            'payments'         => $payments,
            'feeBreakdown'     => $feeBreakdown->values(),
        ]);
    }

    /**
     * Store payment for a student (accounting/admin side).
     *
     * Validates:
     * - Amount doesn't exceed total outstanding balance
     * - Selected term has outstanding balance
     * - Only the first unpaid term of that assessment can be selected
     * - All operations are atomic
     */
    public function storePayment(Request $request, $userId)
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'term_id'        => 'required|exists:student_payment_terms,id',
            'payment_date'   => 'required|date',
        ]);

        $student = User::with('student', 'account')
            ->where('role', 'student')
            ->findOrFail($userId);

        if (!$student->student) {
            return back()->withErrors(['error' => 'Student record not found. Please contact administrator.']);
        }

        $paymentTerm     = StudentPaymentTerm::findOrFail($validated['term_id']);
        $paymentService  = new StudentPaymentService();
        $outstandingBalance = $paymentService->getTotalOutstandingBalance($student);

        if ((float) $validated['amount'] > $outstandingBalance) {
            return back()->withErrors([
                'amount' => sprintf(
                    'Payment amount cannot exceed outstanding balance of ₱%s',
                    number_format($outstandingBalance, 2)
                ),
            ]);
        }

        if ((float) $paymentTerm->balance <= 0) {
            return back()->withErrors([
                'term_id' => 'This payment term has already been paid. Please select another term.',
            ]);
        }

        // Only the first unpaid term of this assessment can be selected
        $firstUnpaidTerm = StudentPaymentTerm::where('student_assessment_id', $paymentTerm->student_assessment_id)
            ->where('balance', '>', 0)
            ->orderBy('term_order')
            ->first();

        if ($firstUnpaidTerm && (int) $paymentTerm->id !== (int) $firstUnpaidTerm->id) {
            return back()->withErrors([
                'term_id' => sprintf(
                    'You must pay "%s" before paying other terms in this semester.',
                    $firstUnpaidTerm->term_name
                ),
            ]);
        }

        try {
            $result = $paymentService->processPayment($student, (float) $validated['amount'], [
                'payment_method'   => $validated['payment_method'],
                'paid_at'          => $validated['payment_date'],
                'description'      => 'Payment recorded by accounting — ' . $paymentTerm->term_name,
                'selected_term_id' => (int) $validated['term_id'],
                'term_name'        => $paymentTerm->term_name,
                'year'             => optional($paymentTerm->assessment)->school_year
                                        ? explode('-', $paymentTerm->assessment->school_year)[0]
                                        : now()->year,
                'semester'         => optional($paymentTerm->assessment)->semester,
            ], false); // Staff-recorded payments bypass approval

            return back()->with('success', 'Payment recorded successfully! ' . $result['message']);

        } catch (\Exception $e) {
            Log::error('Payment recording failed', [
                'user_id' => $userId,
                'term_id' => $validated['term_id'],
                'amount'  => $validated['amount'],
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to record payment. Please try again or contact support.',
            ]);
        }
    }

    /**
     * Edit assessment
     */
    public function edit($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$assessment) {
            return redirect()
                ->route('student-fees.create')
                ->with('info', 'Please create an assessment for this student first.');
        }

        $subjects = Subject::active()
            ->where('course', $student->course)
            ->where('year_level', $student->year_level)
            ->get();

        $fees = Fee::active()
            ->whereIn('category', ['Laboratory', 'Library', 'Athletic', 'Miscellaneous'])
            ->get();

        return Inertia::render('StudentFees/Edit', [
            'student'    => $student,
            'assessment' => $assessment,
            'subjects'   => $subjects,
            'fees'       => $fees,
        ]);
    }

    /**
     * Update student assessment
     */
    public function update(Request $request, $userId)
    {
        $validated = $request->validate([
            'year_level'  => 'required|string',
            'semester'    => 'required|string',
            'school_year' => 'required|string',
            'subjects'    => 'required|array',
            'other_fees'  => 'required|array',
        ]);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $tuitionTotal   = collect($validated['subjects'])->sum('amount') ?? 0;
        $otherFeesTotal = collect($validated['other_fees'])->sum('amount') ?? 0;

        $assessment->update([
            'year_level'       => $validated['year_level'],
            'semester'         => $validated['semester'],
            'school_year'      => $validated['school_year'],
            'subjects'         => $validated['subjects'],
            'fee_breakdown'    => $validated['other_fees'],
            'tuition_fee'      => $tuitionTotal,
            'other_fees'       => $otherFeesTotal,
            'total_assessment' => $tuitionTotal + $otherFeesTotal,
        ]);

        return redirect()
            ->route('student-fees.show', $userId)
            ->with('success', 'Assessment updated successfully!');
    }

    /**
     * Export assessment receipt as a PDF.
     *
     * FIX: $student is already the User model here. The original blade template
     * used `$student->user->account->id` which would error because $student IS
     * the User — it doesn't have a ->user relation. Corrected to `$student->account->id`.
     */
    public function exportPdf($userId)
    {
        // $student is a User model instance (role = 'student')
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessmentId   = request('assessment_id');
        $semesterFilter = request('semester');

        $assessmentQuery = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['paymentTerms' => fn($q) => $q->orderBy('term_order')]);

        if ($assessmentId) {
            $assessmentQuery->where('id', $assessmentId);
        } elseif ($semesterFilter) {
            $assessmentQuery->where('semester', $semesterFilter);
        }

        $assessment = $assessmentQuery->latest()->firstOrFail();

        // Transactions scoped to this assessment's semester
        $transactions = Transaction::where('user_id', $userId)
            ->where(function ($q) use ($assessment) {
                $q->where('semester', $assessment->semester)
                  ->orWhereNull('semester');
            })
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // Payments from the payments table
        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->orderBy('paid_at', 'desc')
            ->get();

        $paymentTerms = $assessment->paymentTerms()->orderBy('term_order')->get();

        $pdf = Pdf::loadView('pdf.student-assessment', [
            'student'      => $student,       // ← User model (has ->account, ->account_id, etc.)
            'assessment'   => $assessment,
            'transactions' => $transactions,
            'payments'     => $payments,
            'paymentTerms' => $paymentTerms,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'receipt-' . $student->account_id . '-' . $assessment->semester . '-' . $assessment->school_year . '.pdf';
        $filename = str_replace([' ', '/'], '-', $filename);

        return $pdf->download($filename);
    }

    /**
     * Show create student form
     */
    public function createStudent()
    {
        $courses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course')
            ->sort()
            ->values();

        if ($courses->isEmpty()) {
            $courses = collect([
                'BS Electrical Engineering Technology',
                'BS Electronics Engineering Technology',
                'BS Computer Science',
                'BS Information Technology',
                'BS Accountancy',
                'BS Business Administration',
            ]);
        }

        return Inertia::render('StudentFees/CreateStudent', [
            'courses'    => $courses,
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        ]);
    }

    /**
     * Store new student
     */
    public function storeStudent(Request $request)
    {
        $validated = $request->validate([
            'last_name'      => 'required|string|max:255',
            'first_name'     => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email'          => 'required|email|unique:users,email',
            'birthday'       => 'required|date',
            'phone'          => 'required|string|max:20',
            'address'        => 'required|string|max:255',
            'year_level'     => 'required|string',
            'course'         => 'required|string',
            'account_id'     => 'nullable|string|unique:users,account_id',
        ]);

        DB::beginTransaction();
        try {
            $studentId = !empty($validated['student_id'])
                ? $validated['student_id']
                : $this->generateUniqueStudentId();

            $user = User::create([
                'last_name'      => $validated['last_name'],
                'first_name'     => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email'          => $validated['email'],
                'birthday'       => $validated['birthday'],
                'phone'          => $validated['phone'],
                'address'        => $validated['address'],
                'year_level'     => $validated['year_level'],
                'course'         => $validated['course'],
                'student_id'     => $studentId,
                'role'           => 'student',
                'status'         => User::STATUS_ACTIVE,
                'password'       => Hash::make('password'),
            ]);

            Student::create(['user_id' => $user->id]);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $user->id)
                ->with('success', 'Student created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create student: ' . $e->getMessage()]);
        }
    }

    private function generateUniqueAccountId(): string
    {
        $year = now()->year;

        return DB::transaction(function () use ($year) {
            $lastStudent = User::where('account_id', 'like', "{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(account_id, 6) AS UNSIGNED) DESC')
                ->first();

            if ($lastStudent) {
                $lastNumber = intval(substr($lastStudent->account_id, -4));
                $newNumber  = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newAccountId = "{$year}-{$newNumber}";

            $attempts = 0;
            while (User::where('account_id', $newAccountId)->exists() && $attempts < 10) {
                $lastNumber   = intval($newNumber);
                $newNumber    = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $newAccountId = "{$year}-{$newNumber}";
                $attempts++;
            }

            if ($attempts >= 10) {
                throw new \Exception('Unable to generate unique account ID after multiple attempts.');
            }

            return $newAccountId;
        });
    }
}