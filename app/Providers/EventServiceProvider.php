<?php

namespace App\Providers;

use App\Events\DueAssigned;
use App\Events\PaymentRecorded;
use App\Listeners\GenerateDueAssignedReminder;
use App\Listeners\GeneratePaymentReceivedReminder;
use App\Listeners\MarkNotificationCompleteOnPayment;
use App\Listeners\SendPaymentConfirmationNotification;
use App\Listeners\SendPaymentDueNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        /**
         * Fired when an admin assigns or updates a due date on a payment term.
         *
         * Listeners (run in order):
         *   1. GenerateDueAssignedReminder  — creates a PaymentReminder record
         *   2. SendPaymentDueNotification   — sends a Laravel database notification
         *      (goes to the proper `notifications` UUID table via Notifiable)
         */
        DueAssigned::class => [
            GenerateDueAssignedReminder::class,
            SendPaymentDueNotification::class,
        ],

        /**
         * Fired when a student payment is recorded/approved.
         *
         * Listeners (run in order):
         *   1. GeneratePaymentReceivedReminder   — creates a PaymentReminder record
         *   2. SendPaymentConfirmationNotification — sends a Laravel database notification
         *   3. MarkNotificationCompleteOnPayment  — marks payment_due admin notifications
         *      complete when the student's full balance is cleared
         */
        PaymentRecorded::class => [
            GeneratePaymentReceivedReminder::class,
            SendPaymentConfirmationNotification::class,
            MarkNotificationCompleteOnPayment::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}