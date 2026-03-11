<?php

namespace App\Http\Controllers;

use App\Events\DueAssigned;
use App\Models\Notification;
use App\Models\StudentPaymentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PaymentTermsController extends Controller
{
    /**
     * Display Payment Terms Management page.
     */
    public function index()
    {
        $this->authorize('managePaymentTerms', StudentPaymentTerm::class);

        $paymentTerms = StudentPaymentTerm::with(['assessment.user.student'])
            ->orderBy('due_date')
            ->get()
            ->map(function ($term) {
                $user    = optional($term->assessment)->user;
                $student = optional($user)->student;

                return [
                    'id'            => $term->id,
                    'term_name'     => $term->term_name,
                    'term_order'    => $term->term_order,
                    'amount'        => (float) $term->amount,
                    'balance'       => (float) $term->balance,
                    'due_date'      => $term->due_date?->toDateString(),
                    'status'        => $term->status,
                    'student_id'    => $student->student_id ?? 'Unknown',
                    'student_name'  => optional($user)->name ?? 'Unknown Student',
                    'assessment_id' => $term->student_assessment_id,
                    'user_id'       => optional($user)->id,
                ];
            });

        $unsetDueDatesCount = StudentPaymentTerm::whereNull('due_date')->count();

        $distinctTermNames = StudentPaymentTerm::query()
            ->select('term_name', 'term_order')
            ->distinct()
            ->orderBy('term_order')
            ->get()
            ->map(fn ($t) => ['term_name' => $t->term_name, 'term_order' => $t->term_order]);

        return Inertia::render('Admin/PaymentTermsManagement', [
            'payment_terms'      => $paymentTerms,
            'unsetDueDatesCount' => $unsetDueDatesCount,
            'distinctTermNames'  => $distinctTermNames,
        ]);
    }

    /**
     * Update the due date for a single payment term.
     *
     * Creates/updates the notification banner AND dispatches DueAssigned event
     * so the student also receives a PaymentReminder feed entry.
     */
    public function updateDueDate(Request $request, StudentPaymentTerm $paymentTerm)
    {
        $this->authorize('update', $paymentTerm);

        $validated = $request->validate([
            'due_date' => 'required|date',
        ]);

        DB::transaction(function () use ($paymentTerm, $validated) {
            $paymentTerm->update(['due_date' => $validated['due_date']]);
            $paymentTerm->refresh();

            $this->upsertDueDateNotification($paymentTerm);
            $this->dispatchDueAssignedIfStudentExists($paymentTerm);
        });

        return back()->with('success', 'Due date updated. Student has been notified.');
    }

    /**
     * Bulk-update due dates for ALL terms sharing the same term_name.
     */
    public function bulkUpdateDueDate(Request $request)
    {
        $this->authorize('managePaymentTerms', StudentPaymentTerm::class);

        $validated = $request->validate([
            'term_name' => 'required|string',
            'due_date'  => 'required|date',
        ]);

        $terms = StudentPaymentTerm::where('term_name', $validated['term_name'])->get();

        if ($terms->isEmpty()) {
            return back()->withErrors(['term_name' => 'No payment terms found with that name.']);
        }

        $updated = 0;

        DB::transaction(function () use ($terms, $validated, &$updated) {
            foreach ($terms as $term) {
                $term->update(['due_date' => $validated['due_date']]);
                $term->refresh();

                try {
                    $this->upsertDueDateNotification($term);
                    $this->dispatchDueAssignedIfStudentExists($term);
                } catch (\Throwable $e) {
                    Log::warning('PaymentTermsController: failed to notify student of due date change', [
                        'term_id' => $term->id,
                        'error'   => $e->getMessage(),
                    ]);
                    // Non-fatal — continue processing remaining terms
                }

                $updated++;
            }
        });

        return back()->with('success', "Due date applied to {$updated} {$validated['term_name']} terms. Students have been notified.");
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Create or update the persistent notification banner for this payment term.
     *
     * One record per (user_id + type + title) — updateOrCreate prevents duplicates
     * when the admin re-sets a due date on the same term.
     *
     * Key decisions:
     *   - trigger_days_before_due = NULL  → notification is visible immediately
     *     (no "only show 7 days before" gate — the student sees it right away)
     *   - end_date = due_date + 14 days   → gives a 2-week grace window after the
     *     deadline before the banner disappears (MarkNotificationCompleteOnPayment
     *     closes it early if the student pays)
     *   - dismissed_at is reset to NULL   → re-opens the banner if admin updates
     *     the due date after a student already dismissed it
     */
    private function upsertDueDateNotification(StudentPaymentTerm $paymentTerm): void
    {
        $assessment = $paymentTerm->assessment;

        if (! $assessment) {
            Log::warning('PaymentTermsController: term has no assessment', ['term_id' => $paymentTerm->id]);
            return;
        }

        $user = $assessment->user;

        if (! $user) {
            Log::warning('PaymentTermsController: assessment has no user', ['assessment_id' => $assessment->id]);
            return;
        }

        $dueDate          = $paymentTerm->due_date;
        $dueDateFormatted = $dueDate->format('F j, Y');
        $amount           = number_format((float) $paymentTerm->amount, 2);

        // Grace window: banner stays visible for 14 days past the due date
        // so overdue students still see the reminder.
        $endDate = $dueDate->copy()->addDays(14)->toDateString();

        Notification::updateOrCreate(
            [
                // Match key: one banner per student per term name
                'user_id' => $user->id,
                'type'    => 'payment_due',
                'title'   => "Payment Due: {$paymentTerm->term_name}",
            ],
            [
                'message'                 => "Your {$paymentTerm->term_name} payment of ₱{$amount} is due on {$dueDateFormatted}. Please settle your balance on or before the deadline.",
                'target_role'             => 'student',
                'start_date'              => now()->toDateString(),
                'end_date'                => $endDate,
                'is_active'               => true,
                'is_complete'             => false,
                'dismissed_at'            => null,   // Re-open if admin updates due date
                'trigger_days_before_due' => null,   // Show immediately, no day-gate
            ]
        );
    }

    /**
     * Fire DueAssigned event if the payment term is linked to a real student user.
     * This triggers:
     *   1. GenerateDueAssignedReminder  → creates a PaymentReminder feed entry
     *   2. SendPaymentDueNotification   → sends a queued mail notification
     */
    private function dispatchDueAssignedIfStudentExists(StudentPaymentTerm $paymentTerm): void
    {
        $user = optional($paymentTerm->assessment)->user;

        if (! $user) {
            return;
        }

        event(new DueAssigned($user, $paymentTerm));
    }
}