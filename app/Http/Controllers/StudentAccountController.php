<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\StudentAssessment;
use App\Models\Notification;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // ── Ensure account row exists ─────────────────────────────────────────
        if (! $user->account) {
            $user->account()->create(['balance' => 0]);
        }

        $user->load(['transactions' => fn ($q) => $q->orderByDesc('created_at')]);

        // ── Latest assessment — source of truth for payment terms tab ─────────
        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->with('paymentTerms')
            ->latest('created_at')
            ->first();

        // ── ALL assessments — needed for enrolled subjects accordion ──────────
        // Ordered newest-first so the most recent semester appears at the top.
        $allAssessments = StudentAssessment::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        // ── Build enrolledSubjectsByAssessment map ────────────────────────────
        // Maps assessmentId → [subject_id, ...] for subjects with status='enrolled'.
        // Mirrors the identical logic in StudentFeeController@show.
        // Drives the "✓ Enrolled / ○ Not Confirmed" badges in the subjects table.
        $enrolledSubjectsByAssessment = [];

        if ($allAssessments->isNotEmpty()) {
            // Build a lookup: "school_year||semester" → assessment model
            $termIndex = $allAssessments->keyBy(
                fn ($a) => $a->school_year . '||' . $a->semester
            );

            // One DB query for all enrolled subjects belonging to this student
            $enrollments = DB::table('student_enrollments')
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->select(['subject_id', 'school_year', 'semester'])
                ->get();

            foreach ($enrollments as $row) {
                $termKey = $row->school_year . '||' . $row->semester;

                if (! isset($termIndex[$termKey])) {
                    // Enrollment belongs to a term with no active assessment — skip
                    continue;
                }

                $assessmentId = $termIndex[$termKey]->id;

                if (! isset($enrolledSubjectsByAssessment[$assessmentId])) {
                    $enrolledSubjectsByAssessment[$assessmentId] = [];
                }

                $enrolledSubjectsByAssessment[$assessmentId][] = $row->subject_id;
            }
        }

        // ── Fees list — from latest assessment fee_breakdown JSON ─────────────
        if ($latestAssessment && ! empty($latestAssessment->fee_breakdown)) {
            $fees = collect($latestAssessment->fee_breakdown)->map(fn ($item) => [
                'name'     => $item['name']     ?? 'Fee',
                'amount'   => (float) ($item['amount'] ?? 0),
                'category' => $item['category'] ?? 'Other',
            ])->values();
        } else {
            $fees = collect();
        }

        // ── Payment terms — ordered for the payment terms table ───────────────
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

        // ── Notifications ─────────────────────────────────────────────────────
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

        // ── Serialise allAssessments for the enrolled-subjects panel ──────────
        // We send the full fee_breakdown so Vue can derive subject rows without
        // a second HTTP request.
        $allAssessmentsSerialized = $allAssessments->map(fn ($a) => [
            'id'                => $a->id,
            'assessment_number' => $a->assessment_number,
            'year_level'        => $a->year_level,
            'semester'          => $a->semester,
            'school_year'       => $a->school_year,
            'course'            => $a->course,
            'total_assessment'  => (float) $a->total_assessment,
            'tuition_fee'       => (float) $a->tuition_fee,
            'other_fees'        => (float) $a->other_fees,
            'fee_breakdown'     => $a->fee_breakdown ?? [],
            'status'            => $a->status,
            'created_at'        => $a->created_at,
        ])->values()->toArray();

        return Inertia::render('Student/AccountOverview', [
            'account'          => $user->account,
            'transactions'     => $user->transactions ?? [],
            'fees'             => $fees->values(),
            'latestAssessment' => $latestAssessment,
            'paymentTerms'     => $paymentTerms,
            'notifications'    => $notifications,

            // Drives the enrolled subjects accordion in AccountOverview
            'allAssessments'               => $allAssessmentsSerialized,
            'enrolledSubjectsByAssessment' => $enrolledSubjectsByAssessment,

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