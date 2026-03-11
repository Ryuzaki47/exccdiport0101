<?php

namespace App\Listeners;

use App\Events\PaymentRecorded;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class MarkNotificationCompleteOnPayment implements ShouldQueue
{
    /**
     * When a payment is recorded, check if the student has fully cleared their
     * balance. If so, mark their payment_due notification banners as complete
     * so they stop appearing on the dashboard.
     *
     * Scoped to type = 'payment_due' only — general announcements are NOT
     * auto-closed when a payment is made.
     */
    public function handle(PaymentRecorded $event): void
    {
        $user              = $event->user;
        $studentAssessment = $user->assessments()->latest('created_at')->first();

        if (! $studentAssessment) {
            return;
        }

        $totalBalance = $studentAssessment->paymentTerms()
            ->where('balance', '>', 0)
            ->sum('balance');

        // Only mark complete when the entire assessment balance is cleared
        if ($totalBalance <= 0) {
            Notification::where('user_id', $user->id)
                ->where('type', 'payment_due')   // Only close payment_due banners
                ->where('is_complete', false)
                ->update(['is_complete' => true]);
        }
    }
}