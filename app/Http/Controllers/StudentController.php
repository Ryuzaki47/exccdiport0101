<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Payment;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentController extends Controller
{
    // ============================================
    // CONSTRUCTOR - Inject WorkflowService
    // ============================================
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    // ============================================
    // INDEX - Display all students
    // ============================================
    public function index(Request $request)
    {
        $query = Student::with(['payments', 'transactions', 'account', 'workflowInstances.workflow']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;

            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('email', 'like', '%' . $searchTerm . '%')
                  ->orWhere('student_id', 'like', '%' . $searchTerm . '%')
                  ->orWhere('student_number', 'like', '%' . $searchTerm . '%')
                  ->orWhere('course', 'like', '%' . $searchTerm . '%')
                  ->orWhere('year_level', 'like', '%' . $searchTerm . '%')
                  ->orWhere('phone', 'like', '%' . $searchTerm . '%')
                  ->orWhere('address', 'like', '%' . $searchTerm . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('enrollment_status', $request->status);
        }

        $students = $query->orderBy('created_at', 'desc')->paginate(10);

        // Fee management has been disabled. Fees are now managed through
        // StudentAssessment fee_breakdown JSON. This hardcoded structure is
        // used only for display context in the Students archive view.
        $fees = collect([
            ['name' => 'Registration Fee', 'amount' => 0.0],
            ['name' => 'Tuition Fee',      'amount' => 0.0],
            ['name' => 'Lab Fee',          'amount' => 0.0],
            ['name' => 'Misc. Fee',        'amount' => 0.0],
        ]);

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters'  => $request->only(['search', 'status']),
            'fees'     => $fees->values(),
        ]);
    }

    // ============================================
    // CREATE - Show create student form
    // ============================================
    public function create()
    {
        return Inertia::render('Students/Create');
    }

    // ============================================
    // STORE - Create new student with workflow
    // ============================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'        => 'required|unique:students',
            'last_name'         => 'required|string|max:255',
            'first_name'        => 'required|string|max:255',
            'middle_initial'    => 'nullable|string|max:10',
            'email'             => 'required|email|unique:students',
            'course'            => 'required|string',
            'year_level'        => 'required|string',
            'birthday'          => 'nullable|date',
            'phone'             => 'nullable|string',
            'address'           => 'nullable|string',
            'total_balance'     => 'nullable|numeric',
            'enrollment_status' => 'sometimes|in:pending,active,suspended,graduated',
            'user_id'           => 'nullable|exists:users,id',
        ]);

        if (!isset($validated['student_number'])) {
            $validated['student_number'] = 'STU-' . strtoupper(uniqid());
        }

        if (!isset($validated['enrollment_status'])) {
            $validated['enrollment_status'] = 'pending';
        }

        // Balance is set to 0 and will be recalculated from StudentAssessment after creation
        $validated['total_balance'] = 0.0;

        $student = Student::create($validated);

        // Auto-start enrollment workflow if status is pending
        if ($student->enrollment_status === 'pending') {
            $workflow = Workflow::active()
                ->where('type', 'student')
                ->where('name', 'like', '%enrollment%')
                ->first();

            if ($workflow) {
                try {
                    $this->workflowService->startWorkflow($workflow, $student, auth()->id());

                    return redirect()->route('students.show', $student)
                        ->with('success', 'Student created and enrollment workflow started');
                } catch (\Exception $e) {
                    logger()->error('Failed to start workflow: ' . $e->getMessage());

                    return redirect()->route('students.show', $student)
                        ->with('warning', 'Student created but workflow failed to start');
                }
            }
        }

        return redirect()->route('students.show', $student)
            ->with('success', 'Student created successfully');
    }

    // ============================================
    // SHOW - Show single student with workflow status
    // ============================================
    public function show($id)
    {
        $student = Student::with([
            'payments',
            'user.account',
            'workflowInstances.workflow',
            'workflowInstances.approvals.approver',
            'accountingTransactions',
        ])->findOrFail($id);

        $activeWorkflow = $student->workflowInstances()
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['workflow', 'approvals'])
            ->first();

        return Inertia::render('Students/StudentProfile', [
            'student'       => $student,
            'activeWorkflow' => $activeWorkflow,
        ]);
    }

    // ============================================
    // STORE PAYMENT - Record payment for student
    // ============================================
    public function storePayment(Request $request, Student $student)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'required|string|max:255',
            'payment_method'   => 'required|string',
            'reference_number' => 'nullable|string',
            'status'           => 'required|string',
            'paid_at'          => 'required|date',
        ]);

        $user = $request->user();

        // Student can only pay for their own record
        if ($user->role === 'student') {
            if (!$user->student || $user->student->id !== $student->id) {
                abort(403, 'Unauthorized payment submission.');
            }
        }

        $student->payments()->create($request->only([
            'amount', 'description', 'payment_method', 'reference_number', 'status', 'paid_at',
        ]));

        return back()->with('success', 'Payment recorded successfully!');
    }

    // ============================================
    // EDIT - Show edit form
    // ============================================
    public function edit(Student $student)
    {
        return Inertia::render('Students/Edit', [
            'student' => $student,
        ]);
    }

    // ============================================
    // UPDATE - Update student
    // ============================================
    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id'        => 'required|string|unique:students,student_id,' . $student->id,
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'middle_initial'    => 'nullable|string|max:10',
            'email'             => 'required|email|unique:students,email,' . $student->id,
            'course'            => 'required|string|max:255',
            'year_level'        => 'required|string',
            'birthday'          => 'nullable|date',
            'phone'             => 'nullable|string|max:20',
            'address'           => 'nullable|string|max:500',
            'total_balance'     => 'required|numeric|min:0',
            'enrollment_status' => 'sometimes|in:pending,active,suspended,graduated',
        ]);

        $statusChanged = $student->enrollment_status !== ($validated['enrollment_status'] ?? $student->enrollment_status);

        $student->update($validated);

        if ($statusChanged && $student->enrollment_status === 'active') {
            $activeWorkflow = $student->workflowInstances()
                ->where('status', 'in_progress')
                ->first();

            if ($activeWorkflow) {
                $activeWorkflow->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);
            }
        }

        return redirect()->route('students.show', $student)
            ->with('success', 'Student updated successfully!');
    }

    // ============================================
    // DESTROY - Delete student
    // ============================================
    public function destroy(Student $student)
    {
        $student->delete();

        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully!');
    }

    // ============================================
    // STUDENT PROFILE - For current logged-in student
    // ============================================
    public function studentProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $student = Student::where('email', $user->email)
                ->orWhere('user_id', $user->id)
                ->firstOrFail();
        } else {
            $student = Student::with('payments')->first();
        }

        $student->load([
            'payments',
            'workflowInstances.workflow',
            'workflowInstances.approvals',
        ]);

        $activeWorkflow = $student->workflowInstances()
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['workflow', 'approvals'])
            ->first();

        return Inertia::render('Students/StudentProfile', [
            'student'        => $student,
            'activeWorkflow' => $activeWorkflow,
        ]);
    }

    // ============================================
    // WORKFLOW ACTIONS
    // ============================================

    /**
     * Manually advance student workflow to next step.
     */
    public function advanceWorkflow(Student $student)
    {
        $activeWorkflow = $student->workflowInstances()
            ->where('status', 'in_progress')
            ->first();

        if (!$activeWorkflow) {
            return back()->withErrors(['error' => 'No active workflow found for this student']);
        }

        try {
            $this->workflowService->advanceWorkflow($activeWorkflow, auth()->id());

            return back()->with('success', 'Workflow advanced to next step');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to advance workflow: ' . $e->getMessage()]);
        }
    }

    /**
     * View workflow history for student.
     */
    public function workflowHistory(Student $student)
    {
        $workflows = $student->workflowInstances()
            ->with(['workflow', 'approvals.approver'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Students/WorkflowHistory', [
            'student'   => $student,
            'workflows' => $workflows,
        ]);
    }
}