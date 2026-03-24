<?php

require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Workflow;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Models\Transaction;
use App\Models\WorkflowInstance;
use App\Enums\UserRoleEnum;

echo "=== APPROVAL SYSTEM AUDIT ===\n\n";

// Check 1: Payment approval workflow exists and is active
echo "STEP 1: Workflow Configuration\n";
echo "───────────────────────────────\n";
$workflow = Workflow::where('type', 'payment_approval')->first();
if ($workflow) {
    echo "✅ Workflow EXISTS (ID: {$workflow->id}, Active: {$workflow->is_active})\n";
    echo "   Type: {$workflow->type}\n";
    echo "   Steps: " . count($workflow->steps) . "\n";
    foreach ($workflow->steps as $step) {
        $req = isset($step['requires_approval']) && $step['requires_approval'] ? 'true' : 'false';
        echo "   - {$step['name']}: requires_approval=$req";
        if (isset($step['approver_role'])) {
            echo ", approver_role={$step['approver_role']}";
        }
        echo "\n";
    }
} else {
    echo "❌ NO WORKFLOW FOUND\n";
    echo "   Run: php artisan db:seed --class=PaymentApprovalWorkflowSeeder\n";
}

// Check 2: Accounting users exist
echo "\nSTEP 2: Approver Users\n";
echo "──────────────────────\n";
$accountingUsers = User::where('role', UserRoleEnum::ACCOUNTING->value)->where('is_active', true)->get();
$adminUsers = User::where('role', UserRoleEnum::ADMIN->value)->where('is_active', true)->get();
$accountingCount = $accountingUsers->count();
$adminCount = $adminUsers->count();

echo "Active Accounting Users: $accountingCount\n";
if ($accountingCount > 0) {
    foreach ($accountingUsers as $u) {
        echo "  - {$u->email} ({$u->id})\n";
    }
}

echo "Active Admin Users: $adminCount\n";
if ($adminCount > 0) {
    foreach ($adminUsers as $u) {
        echo "  - {$u->email} ({$u->id})\n";
    }
}

if ($accountingCount === 0 && $adminCount === 0) {
    echo "❌ CRITICAL: NO APPROVERS FOUND\n";
}

// Check 3: Pending transactions vs approvals
echo "\nSTEP 3: Transaction & Approval State\n";
echo "─────────────────────────────────────\n";
$pendingTransactions = Transaction::where('status', 'awaiting_approval')->count();
$pendingApprovals = WorkflowApproval::where('status', 'pending')->count();
$totalApprovals = WorkflowApproval::count();

echo "Transactions with status='awaiting_approval': $pendingTransactions\n";
echo "WorkflowApprovals with status='pending': $pendingApprovals\n";
echo "WorkflowApprovals (all statuses): $totalApprovals\n";

if ($pendingTransactions > 0 && $pendingApprovals === 0) {
    echo "\n⚠️  CRITICAL: Pending transactions exist but NO approvals created!\n";
    
    // Check for transactions without workflow instances
    $missingWorkflows = Transaction::where('status', 'awaiting_approval')
        ->whereDoesntHave('workflowInstance')
        ->count();
    echo "    Transactions missing workflow instances: $missingWorkflows\n";
    
    if ($missingWorkflows > 0) {
        echo "\n    → This suggests startPaymentApprovalWorkflow() was not called or failed.\n";
    }
}

// Show sample data
if ($pendingTransactions > 0) {
    echo "\nSTEP 4: Sample Pending Transactions\n";
    echo "────────────────────────────────────\n";
    $samples = Transaction::where('status', 'awaiting_approval')->latest()->take(3)->get();
    foreach ($samples as $t) {
        echo "\n📌 Transaction ID: {$t->id}\n";
        echo "   Amount: {$t->amount}, User ID: {$t->user_id}\n";
        echo "   Created: {$t->created_at}\n";
        echo "   Reference: {$t->reference}\n";
        
        $wi = $t->workflowInstance;
        if ($wi) {
            echo "   ✅ WorkflowInstance EXISTS\n";
            echo "      ID: {$wi->id}\n";
            echo "      Status: {$wi->status}\n";
            echo "      Current Step: {$wi->current_step}\n";
            
            $approvals = $wi->approvals;
            echo "      Approvals: " . $approvals->count() . "\n";
            foreach ($approvals as $a) {
                $approver = User::find($a->approver_id);
                $approverName = $approver ? $approver->email : "DELETED USER";
                echo "         └─ {$approverName} (ID: {$a->approver_id}, Status: {$a->status})\n";
            }
        } else {
            echo "   ❌ NO WORKFLOW INSTANCE\n";
            echo "      → Transaction will NOT appear in /approvals\n";
        }
    }
} else {
    echo "\nSTEP 4: No Pending Transactions\n";
    echo "────────────────────────────────\n";
    echo "No transactions with status='awaiting_approval' found.\n";
    echo "Test by submitting a student payment.\n";
}

// Query test: What does WorkflowApprovalController::index see?
echo "\nSTEP 5: Accounting View Simulation\n";
echo "──────────────────────────────────\n";
if ($accountingUsers->count() > 0) {
    $accountingUser = $accountingUsers->first();
    echo "Simulating view for: {$accountingUser->email}\n\n";
    
    $visible = WorkflowApproval::query()
        ->with(['workflowInstance.workflow', 'workflowInstance.workflowable'])
        ->whereHas('workflowInstance.workflow', function ($wq) {
            $wq->where('type', 'payment_approval');
        })
        ->latest()
        ->get();
    
    echo "Approvals visible on /approvals page: " . $visible->count() . "\n";
    
    if ($visible->count() > 0) {
        echo "\nFirst 3 visible approvals:\n";
        foreach ($visible->take(3) as $a) {
            echo "  - ID: {$a->id}, Status: {$a->status}, Step: {$a->step_name}\n";
            if ($a->workflowInstance && $a->workflowInstance->workflowable) {
                echo "    Transaction: " . $a->workflowInstance->workflowable->reference . "\n";
            }
        }
    }
} else {
    echo "⚠️  Cannot simulate: No active accounting users\n";
}

echo "\n=== SUMMARY ===\n";
$issues = [];

if (!$workflow) {
    $issues[] = "❌ Payment approval workflow not found";
}
if ($accountingCount === 0 && $adminCount === 0) {
    $issues[] = "❌ No active approver users (accounting or admin)";
}
if ($pendingTransactions > 0) {
    $missingWf = Transaction::where('status', 'awaiting_approval')
        ->whereDoesntHave('workflowInstance')->count();
    if ($missingWf > 0) {
        $issues[] = "❌ $missingWf pending transaction(s) missing workflow instances";
    }
}
if ($pendingTransactions > 0 && $pendingApprovals === 0) {
    $issues[] = "❌ Pending transactions exist but NO approvals to display";
}

if (empty($issues)) {
    echo "✅ System appears healthy\n";
} else {
    echo "Issues found:\n";
    foreach ($issues as $issue) {
        echo "  $issue\n";
    }
}

echo "\n";
