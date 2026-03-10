<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\StudentAssessment;
use App\Models\PaymentReminder;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Get account with transactions
        $account = $user->account()->with('transactions')->first();
        
        if (!$account) {
            $account = $user->account()->create(['balance' => 0]);
        }

        // Get latest assessment with payment terms (MOST ACCURATE DATA)
        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->with('paymentTerms')
            ->latest('created_at')
            ->first();

        // Calculate remaining balance from payment terms (if available)
        // This is the source of truth for financial data
        $remainingBalance = 0;
        $paymentTerms = collect([]);
        
        if ($latestAssessment) {
            $paymentTerms = $latestAssessment->paymentTerms()
                ->orderBy('term_order')
                ->get();
            
            // Sum all remaining balances from payment terms
            $remainingBalance = $paymentTerms->sum('balance');
        }

        // Fallback to transaction-based calculation if no payment terms
        if (empty($paymentTerms)) {
            $totalCharges = $user->transactions()->where('kind', 'charge')->sum('amount');
            $totalPayments = $user->transactions()
                ->where('kind', 'payment')
                ->where('status', 'paid')
                ->sum('amount');
            $remainingBalance = max(0, $totalCharges - $totalPayments);
        } else {
            // Calculate totals from actual transactions for accuracy
            $totalCharges = $user->transactions()->where('kind', 'charge')->sum('amount');
            $totalPayments = $user->transactions()
                ->where('kind', 'payment')
                ->where('status', 'paid')
                ->sum('amount');
        }

        // Pending charges count
        $pendingChargesCount = $user->transactions()
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();

        // Get active notifications (role-based and user-specific)
        $notifications = Notification::active()
            ->forUser($user->id)
            ->withinDateRange()
            ->forDueDateTrigger($user)
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'start_date' => $notification->start_date,
                    'end_date' => $notification->end_date,
                    'target_role' => $notification->target_role,
                    'is_active' => $notification->is_active,
                    'is_complete' => $notification->is_complete,
                ];
            });

        // Get recent transactions
        $recentTransactions = $user->transactions()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'reference' => $txn->reference,
                    'type' => $txn->type ?: 'General',
                    'amount' => $txn->amount,
                    'status' => $txn->status,
                    'created_at' => $txn->created_at,
                ];
            });

        // Use assessment total for total_fees (matches AccountOverview logic)
        $totalFees = $latestAssessment ? (float) $latestAssessment->total_assessment : (float) ($totalCharges ?? 0);

        // Get unread payment reminders
        $unreadReminders = PaymentReminder::where('user_id', $user->id)
            ->where('status', '!=', PaymentReminder::STATUS_DISMISSED)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($reminder) {
                return [
                    'id' => $reminder->id,
                    'type' => $reminder->type,
                    'message' => $reminder->message,
                    'outstanding_balance' => (float) $reminder->outstanding_balance,
                    'status' => $reminder->status,
                    'read_at' => $reminder->read_at,
                    'sent_at' => $reminder->sent_at,
                    'trigger_reason' => $reminder->trigger_reason,
                ];
            });

        // Count unread reminders
        $unreadReminderCount = PaymentReminder::where('user_id', $user->id)
            ->where('status', PaymentReminder::STATUS_SENT)
            ->count();

        return Inertia::render('Student/Dashboard', [
            'account' => $account,
            'notifications' => $notifications,
            'recentTransactions' => $recentTransactions,
            'latestAssessment' => $latestAssessment ? [
                'id' => $latestAssessment->id,
                'assessment_number' => $latestAssessment->assessment_number,
                'total_assessment' => (float) $latestAssessment->total_assessment,
                'status' => $latestAssessment->status,
                'created_at' => $latestAssessment->created_at,
            ] : null,
            'paymentTerms' => $paymentTerms->map(function ($term) {
                return [
                    'id' => $term->id,
                    'term_name' => $term->term_name,
                    'term_order' => $term->term_order,
                    'percentage' => $term->percentage,
                    'amount' => (float) $term->amount,
                    'balance' => (float) $term->balance,
                    'due_date' => $term->due_date,
                    'status' => $term->status,
                    'remarks' => $term->remarks,
                    'paid_date' => $term->paid_date,
                ];
            })->toArray(),
            'stats' => [
                'total_fees' => $totalFees,
                'total_paid' => (float) ($totalPayments ?? 0),
                'remaining_balance' => (float) $remainingBalance,
                'pending_charges_count' => $pendingChargesCount,
            ],
            'paymentReminders' => $unreadReminders,
            'unreadReminderCount' => $unreadReminderCount,
        ]);
    }
}