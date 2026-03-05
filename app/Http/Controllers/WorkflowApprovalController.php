<?php

namespace App\Http\Controllers;

use App\Models\WorkflowApproval;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowApprovalController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $userRole = $user->role->value ?? null;

        // Show approvals directly assigned to this user.
        // Additionally, accounting and admin users can see every approval that
        // belongs to a step with approver_role = 'accounting', so no submission
        // falls through the cracks even if the approval record was created before
        // the user existed (mirrors the logic in WorkflowApprovalPolicy).
        $approvals = WorkflowApproval::query()
            ->with([
                'workflowInstance.workflow',
                'workflowInstance.workflowable.user',
            ])
            ->where(function ($query) use ($user, $userRole) {
                $query->where('approver_id', $user->id);

                if (in_array($userRole, ['accounting', 'admin'])) {
                    // Also include pending approvals on steps whose approver_role
                    // is 'accounting', regardless of who was originally assigned.
                    $query->orWhereHas('workflowInstance.workflow', function ($wq) {
                        $wq->whereJsonContains('steps', ['approver_role' => 'accounting']);
                    });
                }
            })
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return Inertia::render('Approvals/Index', [
            'approvals' => $approvals,
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(WorkflowApproval $approval)
    {
        $this->authorize('view', $approval);

        $approval->load([
            'workflowInstance.workflow',
            'workflowInstance.workflowable',
            'workflowInstance.approvals',
        ]);

        return Inertia::render('Approvals/Show', [
            'approval' => $approval,
        ]);
    }

    public function approve(Request $request, WorkflowApproval $approval)
    {
        $this->authorize('approve', $approval);

        if ($approval->status !== 'pending') {
            return back()->withErrors(['error' => 'This approval has already been processed']);
        }

        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        $this->workflowService->approveStep(
            $approval,
            auth()->id(),
            $validated['comments'] ?? null
        );

        return redirect()->route('approvals.index')
            ->with('success', 'Approval granted successfully');
    }

    public function reject(Request $request, WorkflowApproval $approval)
    {
        $this->authorize('approve', $approval);

        if ($approval->status !== 'pending') {
            return back()->withErrors(['error' => 'This approval has already been processed']);
        }

        $validated = $request->validate([
            'comments' => 'required|string|max:1000',
        ]);

        $this->workflowService->rejectStep(
            $approval,
            auth()->id(),
            $validated['comments']
        );

        return redirect()->route('approvals.index')
            ->with('success', 'Approval rejected');
    }
}