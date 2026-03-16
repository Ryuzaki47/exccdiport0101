<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentAssessment;
use App\Models\Notification;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (! $user->account) {
            $user->account()->create(['balance' => 0]);
        }

        $user->load(['transactions' => fn ($q) => $q->orderByDesc('created_at')]);

        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->with('paymentTerms')
            ->latest('created_at')
            ->first();

        // FIX (Bug #7): Build the fees list from the student's actual assessment
        // fee_breakdown JSON. The previous code had a hardcoded array with
        // fictitiously low amounts (₱5,000 tuition) that were shown to the student
        // even when a real assessment existed, causing financial confusion.
        //
        // If no assessment exists yet, return an empty collection so the frontend
        // can render a "No assessment yet" state instead of fake placeholder data.
        if ($latestAssessment && ! empty($latestAssessment->fee_breakdown)) {
            $fees = collect($latestAssessment->fee_breakdown)->map(fn ($item) => [
                'name'     => $item['name']     ?? 'Fee',
                'amount'   => (float) ($item['amount'] ?? 0),
                'category' => $item['category'] ?? 'Other',
            ])->values();
        } else {
            $fees = collect();
        }

        $paymentTerms = [];
        if ($latestAssessment) {
            $paymentTerms = $latestAssessment->paymentTerms()
                ->orderBy('term_order')
                ->get()
                ->map(fn ($t) => [
                    'id'         => $t->id,
                    'term_name'  => $t->term_name,
                    'term_order' => $t->term_order,
                    'percentage' => $t->percentage,
                    'amount'     => (float) $t->amount,
                    'balance'    => (float) $t->balance,
                    'due_date'   => $t->due_date,
                    'status'     => $t->status,
                    'remarks'    => $t->remarks,
                    'paid_date'  => $t->paid_date,
                ])
                ->toArray();
        }

        $notifications = Notification::active()
            ->forUser($user->id)
            ->withinDateRange()
            ->forDueDateTrigger($user)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($n) => [
                'id'              => $n->id,
                'title'           => $n->title,
                'message'         => $n->message,
                'type'            => $n->type,
                'start_date'      => $n->start_date,
                'end_date'        => $n->end_date,
                'due_date'        => $n->due_date,
                'payment_term_id' => $n->payment_term_id,
                'target_role'     => $n->target_role,
                'user_id'         => $n->user_id,
                'is_active'       => $n->is_active,
                'is_complete'     => $n->is_complete,
                'dismissed_at'    => $n->dismissed_at,
                'created_at'      => $n->created_at,
            ]);

        return Inertia::render('Student/AccountOverview', [
            'account'          => $user->account,
            'transactions'     => $user->transactions ?? [],
            'fees'             => $fees->values(),
            'latestAssessment' => $latestAssessment,
            'paymentTerms'     => $paymentTerms,
            'notifications'    => $notifications,

            'pendingApprovalPayments' => $user->transactions
                ->filter(fn ($t) => $t->kind === 'payment' && $t->status === 'awaiting_approval')
                ->map(fn ($t) => [
                    'id'               => $t->id,
                    'reference'        => $t->reference,
                    'amount'           => (float) $t->amount,
                    'selected_term_id' => isset($t->meta['selected_term_id'])
                        ? (int) $t->meta['selected_term_id']
                        : null,
                    'term_name'        => $t->meta['term_name'] ?? 'General',
                    'created_at'       => $t->created_at,
                ])
                ->values(),
        ]);
    }
}