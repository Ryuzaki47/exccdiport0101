use App\Models\Workflow;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Models\Transaction;
use App\Models\WorkflowInstance;
use App\Enums\UserRoleEnum;

echo "\n=== APPROVAL SYSTEM AUDIT ===\n\n";

// Check 1: Payment approval workflow
$workflow = Workflow::where('type', 'payment_approval')->first();
if ($workflow) {
    echo "✅ Workflow EXISTS: ID {$workflow->id}, Active: {$workflow->is_active}\n";
    echo "   Steps: " . count($workflow->steps) . "\n";
} else {
    echo "❌ NO WORKFLOW - Run: php artisan db:seed --class=PaymentApprovalWorkflowSeeder\n";
}

// Check 2: Approvers
$accountingCount = User::where('role', UserRoleEnum::ACCOUNTING->value)->where('is_active', true)->count();
$adminCount = User::where('role', UserRoleEnum::ADMIN->value)->where('is_active', true)->count();
echo "\nActive Approvers: Accounting=$accountingCount, Admin=$adminCount\n";

// Check 3: Transactions vs Approvals
$pending = Transaction::where('status', 'awaiting_approval')->count();
$pendings = WorkflowApproval::where('status', 'pending')->count();
echo "\nPending Transactions: $pending\n";
echo "Pending Approvals: $pendings\n";

if ($pending > 0 && $pendings === 0) {
    $missing = Transaction::where('status', 'awaiting_approval')->whereDoesntHave('workflowInstance')->count();
    echo "⚠️  CRITICAL: $missing transactions missing workflows!\n";
}

// Check 4: Sample data
if ($pending > 0) {
    echo "\n=== SAMPLE TRANSACTIONS ===\n";
    $samples = Transaction::where('status', 'awaiting_approval')->latest()->take(2)->get();
    foreach ($samples as $t) {
        echo "\nTransaction {$t->id}: Amount \${$t->amount}\n";
        if ($t->workflowInstance) {
            echo "  ✅ Has WorkflowInstance\n";
            echo "     Approvals: " . $t->workflowInstance->approvals->count() . "\n";
        } else {
            echo "  ❌ NO WorkflowInstance\n";
        }
    }
}

echo "\n";
