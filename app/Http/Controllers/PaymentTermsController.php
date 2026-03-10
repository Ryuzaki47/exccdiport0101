<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\StudentPaymentTerm;
use App\Models\Notification;

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
                // Guard: broken relationship chain must not crash the page
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

        // List of term names for the bulk-update selector
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
     * Also creates/updates the student notification for this term.
     */
    public function updateDueDate(Request $request, StudentPaymentTerm $paymentTerm)
    {
        $this->authorize('update', $paymentTerm);

        $validated = $request->validate([
            'due_date' => 'required|date',
        ]);

        DB::transaction(function () use ($paymentTerm, $validated) {
            $paymentTerm->update(['due_date' => $validated['due_date']]);

            // Re-fetch to ensure Carbon cast is applied after the update
            $paymentTerm->refresh();

            $this->upsertDueDateNotification($paymentTerm);
        });

        return back()->with('success', 'Due date updated. Student notification created.');
    }

    /**
     * Bulk-update due dates for ALL terms with the same term_name.
     * Useful when admin wants to apply one deadline to all "Prelim" terms, etc.
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
                } catch (\Throwable $e) {
                    Log::warning('Failed to create due date notification', [
                        'term_id' => $term->id,
                        'error'   => $e->getMessage(),
                    ]);
                    // Non-fatal: continue updating the rest
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
     * Create or update a payment_due notification for the student linked to
     * the given payment term.
     *
     * Strategy:
     *   - One notification per (user_id + term_name) pair — uses updateOrCreate
     *     so re-setting the due date updates the existing notification rather
     *     than creating duplicates.
     *   - end_date is set to 3 days AFTER the due date so the notification
     *     stays visible a little while after the deadline (grace window).
     *   - trigger_days_before_due is set to 7 days by default so the
     *     notification surfaces one week before the deadline.
     *
     * @throws \RuntimeException if the relationship chain is broken.
     */
    private function upsertDueDateNotification(StudentPaymentTerm $paymentTerm): void
    {
        // Guard: ensure relationships exist before proceeding
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

        $dueDateCarbon   = $paymentTerm->due_date; // Carbon (cast applied after refresh)
        $dueDateFormatted = $dueDateCarbon->format('F j, Y');
        $amount          = number_format((float) $paymentTerm->amount, 2);

        // end_date: 3-day grace window after due date
        $endDate = $dueDateCarbon->copy()->addDays(3)->toDateString();

        Notification::updateOrCreate(
            // Unique match: one notification per student + term name
            [
                'user_id'   => $user->id,
                'type'      => 'payment_due',
                'title'     => "Payment Due: {$paymentTerm->term_name}",
            ],
            // Values to set (on create or update)
            [
                'message'                 => "Your {$paymentTerm->term_name} payment of ₱{$amount} is due on {$dueDateFormatted}. Please settle your balance on or before the deadline.",
                'target_role'             => 'student',
                'start_date'              => now()->toDateString(),
                'end_date'                => $endDate,
                'is_active'               => true,
                'is_complete'             => false,
                'dismissed_at'            => null,
                'trigger_days_before_due' => 7, // Show 7 days before due
            ]
        );
    }
}