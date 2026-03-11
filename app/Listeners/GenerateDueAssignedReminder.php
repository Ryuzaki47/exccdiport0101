<?php

namespace App\Listeners;

use App\Events\DueAssigned;
use App\Models\PaymentReminder;
use Illuminate\Support\Facades\Auth;

class GenerateDueAssignedReminder
{
    /**
     * Handle the DueAssigned event.
     *
     * Creates or updates a PaymentReminder record for the student's feed.
     * Uses updateOrCreate keyed on (user_id + term_id + type) to prevent
     * duplicate reminder entries when the admin updates the same due date twice.
     */
    public function handle(DueAssigned $event): void
    {
        $user = $event->user;
        $term = $event->term;

        // Calculate days until due (negative = already overdue)
        $daysUntilDue = now()->diffInDays($term->due_date, false);

        // Determine reminder type and message based on urgency
        if ($daysUntilDue < 0) {
            $type    = PaymentReminder::TYPE_OVERDUE;
            $message = "{$term->term_name} is overdue by " . abs((int) $daysUntilDue) . " day(s). Amount due: ₱" . number_format($term->balance, 2);
        } elseif ($daysUntilDue <= 3) {
            $type    = PaymentReminder::TYPE_APPROACHING_DUE;
            $message = "{$term->term_name} is due in {$daysUntilDue} day(s). Amount due: ₱" . number_format($term->balance, 2);
        } else {
            $type    = PaymentReminder::TYPE_PAYMENT_DUE;
            $message = "{$term->term_name} payment due on " . $term->due_date->format('M d, Y') . ". Amount: ₱" . number_format($term->balance, 2);
        }

        // updateOrCreate prevents duplicate rows when admin updates the same term's
        // due date multiple times. The record is refreshed each time.
        PaymentReminder::updateOrCreate(
            [
                'user_id'                  => $user->id,
                'student_payment_term_id'  => $term->id,
                'type'                     => $type,
            ],
            [
                'student_assessment_id' => $term->student_assessment_id,
                'message'               => $message,
                'outstanding_balance'   => $term->balance,
                'status'                => PaymentReminder::STATUS_SENT,
                'in_app_sent'           => true,
                'sent_at'               => now(),
                'trigger_reason'        => PaymentReminder::TRIGGER_DUE_DATE_CHANGE,
                'triggered_by'          => Auth::id(),
                'metadata'              => [
                    'term_order'  => $term->term_order,
                    'due_date'    => $term->due_date?->toDateString(),
                    'percentage'  => $term->percentage,
                ],
            ]
        );
    }
}