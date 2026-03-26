<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Custom Admin Notification Model
 * 
 * CONCERN #3 FIX: Dual Notification System Clarification
 * 
 * This model represents CUSTOM BROADCAST NOTIFICATIONS for CCDI, stored in the
 * `admin_notifications` table. This is SEPARATE from Laravel's built-in database
 * notification channel (which uses the `notifications` table).
 * 
 * Two Notification Systems in CCDI Account Portal:
 * 
 * 1. LARAVEL DATABASE NOTIFICATIONS (notifications table)
 *    - For: Transactional, user-specific events
 *    - Created via: $user->notify(new SomeNotification(...))
 *    - Examples: ApprovalRequired, PaymentDueNotification, PaymentConfirmed
 *    - Features: Multi-channel (mail, database, SMS)
 * 
 * 2. CUSTOM ADMIN NOTIFICATIONS (admin_notifications table) — THIS CLASS
 *    - For: System announcements, role-based broadcasts, admin-actionable events
 *    - Created via: Notification::create([...])
 *    - Examples: "Assessment Required", "Progression Ready", "Payment Approved"
 *    - Features: Role targeting, time windows, CCDI-specific context
 * 
 * ⚠️ IMPORTANT: Do NOT store Laravel notifications in this model. Use the
 * Laravel Notification facade and $user->notify() for transactional events.
 * 
 * See: docs/NOTIFICATION_ARCHITECTURE.md for complete guidance
 */
class Notification extends Model
{
    use HasFactory;

    /**
     * Points to `admin_notifications` — the custom admin announcements table.
     * Kept separate from Laravel's built-in `notifications` table.
     */
    protected $table = 'admin_notifications';

    protected $fillable = [
        'title',
        'message',
        'type',
        'start_date',
        'end_date',
        'due_date',
        'payment_term_id',
        'target_role',
        'user_id',
        'is_active',
        'is_complete',
        'dismissed_at',
        'term_ids',
        'target_term_name',
        'trigger_days_before_due',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'due_date'     => 'date',
        'is_active'    => 'boolean',
        'is_complete'  => 'boolean',
        'dismissed_at' => 'datetime',
        'term_ids'     => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(StudentPaymentTerm::class, 'payment_term_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where('is_complete', false)
            ->whereNull('dismissed_at');
    }

    /**
     * Scope: notifications visible to a given user.
     *
     * Matches:
     *   1. Notifications directly addressed to this user (user_id = $user->id)
     *   2. Role/broadcast notifications for this user's role — with optional
     *      term-name or term-ID filtering:
     *        a. target_term_name is set → student must have an unpaid term
     *           with that name in their latest assessment
     *        b. term_ids is set → student must have an unpaid term whose ID
     *           is in the term_ids JSON array
     *        c. Neither set → visible to all students of the matching role
     */
    public function scopeForUser($query, int|string $userIdentifier)
    {
        if (is_string($userIdentifier) && str_contains($userIdentifier, '@')) {
            $user = User::where('email', $userIdentifier)->first();
        } else {
            $user = User::find($userIdentifier);
        }

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($q) use ($user) {
            // Case 1: directly addressed to this specific user
            $q->where('user_id', $user->id)

            // Case 2: broadcast notification for this role
              ->orWhere(function ($q2) use ($user) {
                  $roleString = $user->role instanceof \BackedEnum
                      ? $user->role->value
                      : (string) $user->role;

                  $q2->whereNull('user_id')
                     ->where(function ($q3) use ($user, $roleString) {
                         $q3->where('target_role', $roleString)
                            ->orWhere('target_role', 'all');
                     })
                     // Apply term-name filter if set
                     ->where(function ($q4) use ($user) {
                         $q4->whereNull('target_term_name')
                            ->orWhereExists(function ($sub) use ($user) {
                                $sub->from('student_payment_terms')
                                    ->join(
                                        'student_assessments',
                                        'student_assessments.id',
                                        '=',
                                        'student_payment_terms.student_assessment_id'
                                    )
                                    ->where('student_assessments.user_id', $user->id)
                                    ->whereColumn(
                                        'student_payment_terms.term_name',
                                        'admin_notifications.target_term_name'
                                    );
                            });
                     })
                     // Apply term-IDs filter if set
                     ->where(function ($q5) use ($user) {
                         $table = (new self())->getTable();
                         $driver = \Illuminate\Support\Facades\DB::getDriverName();

                         $q5->whereNull('term_ids')
                            ->orWhereRaw("JSON_LENGTH({$table}.term_ids) = 0")
                            ->orWhereExists(function ($sub) use ($user, $table, $driver) {
                                $sub->from('student_payment_terms')
                                    ->join(
                                        'student_assessments',
                                        'student_assessments.id',
                                        '=',
                                        'student_payment_terms.student_assessment_id'
                                    )
                                    ->where('student_assessments.user_id', $user->id)
                                    ->whereRaw(
                                        $driver === 'sqlite'
                                            ? "EXISTS (SELECT 1 FROM json_each({$table}.term_ids) WHERE json_each.value = student_payment_terms.id)"
                                            : "JSON_CONTAINS({$table}.term_ids, CAST(student_payment_terms.id AS JSON))"
                                    );
                            });
                     });
              });
        });
    }

    public function scopeWithinDateRange($query)
    {
        $today = now()->toDateString();

        return $query
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $today);
            });
    }

    public function scopeForDueDateTrigger($query, User $user)
    {
        $today        = now()->toDateString();
        $maxLookahead = now()->addDays(90)->toDateString();
        $table        = $this->getTable();

        return $query->where(function ($q) use ($user, $today, $maxLookahead, $table) {
            $q->whereNull('trigger_days_before_due')
              ->orWhere(function ($q2) use ($user, $today, $maxLookahead, $table) {
                  $q2->whereNotNull('trigger_days_before_due')
                     ->whereExists(function ($sub) use ($user, $today, $maxLookahead, $table) {
                         $sub->from('student_payment_terms')
                             ->join(
                                 'student_assessments',
                                 'student_assessments.id',
                                 '=',
                                 'student_payment_terms.student_assessment_id'
                             )
                             ->where('student_assessments.user_id', $user->id)
                             ->where('student_payment_terms.balance', '>', 0)
                             ->whereNotNull('student_payment_terms.due_date')
                             ->where('student_payment_terms.due_date', '>=', $today)
                             ->where('student_payment_terms.due_date', '<=', $maxLookahead)
                             ->whereRaw(
                                 'student_payment_terms.due_date <= ' .
                                 self::addDaysExpression("{$table}.trigger_days_before_due")
                             );
                     });
              });
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isCurrentlyActive(): bool
    {
        $today = now()->toDateString();

        return $this->is_active
            && ! $this->is_complete
            && ! $this->dismissed_at
            && (! $this->start_date || $this->start_date->toDateString() <= $today)
            && (! $this->end_date   || $this->end_date->toDateString()   >= $today);
    }

    public function markComplete(): void
    {
        $this->update(['is_complete' => true]);
    }

    public function markDismissed(): void
    {
        $this->update(['dismissed_at' => now()]);
    }

    public static function addDaysExpression(string $columnExpression): string
    {
        $driver = \Illuminate\Support\Facades\Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return "DATE('now', '+' || {$columnExpression} || ' days')";
        }

        return "DATE_ADD(CURDATE(), INTERVAL {$columnExpression} DAY)";
    }
}