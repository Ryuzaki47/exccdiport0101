<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use App\Services\StudentPaymentService;

class PaymentController extends Controller
{
    /**
     * Show the student payment creation page.
     *
     * Passes all data the Vue component needs:
     * - studentName        : full name for display
     * - outstandingBalance : total unpaid balance (from payment terms)
     * - paymentTerms       : all terms so the student can pick which one to pay
     * - latestAssessment   : assessment metadata (school year, semester, etc.)
     * - pendingApprovalPayments : any payments already awaiting accounting approval
     */
    public function create()
    {
        $user = auth()->user();

        // Resolve latest active assessment and its payment terms
        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('paymentTerms')
            ->latest()
            ->first();

        $paymentTerms = [];
        if ($latestAssessment) {
            $paymentTerms = $latestAssessment
                ->paymentTerms()
                ->orderBy('term_order')
                ->get()
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
                ->toArray();
        }

        // Compute outstanding balance from payment terms (source of truth)
        $outstandingBalance = collect($paymentTerms)->sum('balance');

        // Pending approval transactions for this user
        $pendingApprovalPayments = $user->transactions()
            ->where('kind', 'payment')
            ->where('status', 'awaiting_approval')
            ->get()
            ->map(fn($t) => [
                'id'               => $t->id,
                'reference'        => $t->reference,
                'amount'           => (float) $t->amount,
                'selected_term_id' => isset($t->meta['selected_term_id'])
                    ? (int) $t->meta['selected_term_id']
                    : null,
                'term_name'        => $t->meta['term_name'] ?? 'General',
                'created_at'       => $t->created_at,
            ])
            ->values();

        return Inertia::render('Payment/Create', [
            'studentName'            => $user->name,
            'outstandingBalance'     => (float) $outstandingBalance,
            'paymentTerms'           => $paymentTerms,
            'latestAssessment'       => $latestAssessment,
            'pendingApprovalPayments' => $pendingApprovalPayments,
        ]);
    }
}