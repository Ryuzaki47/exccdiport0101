<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Notification extends Model
{
    use HasFactory;

    /**
     * The table used for custom admin announcements.
     * Laravel's built-in database notification channel uses the `notifications`
     * table (UUID-keyed). This model uses `admin_notifications` to avoid collision.
     */
    protected $table = 'admin_notifications';

    protected $fillable = [
        'title',
        'message',
        'type',
        'start_date',
        'end_date',
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

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only non-dismissed, active, incomplete notifications.
     */
    public function scopeActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where('is_complete', false)
            ->whereNull('dismissed_at');
    }

    /**
     * Scope: notifications visible to a given user (by ID or email).
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
            $q->where('user_id', $user->id)
              ->orWhere(function ($q2) use ($user) {
                  $q2->whereNull('user_id')
                     ->where(function ($q3) use ($user) {
                         $q3->where('target_role', $user->role)
                            ->orWhere('target_role', 'all');
                     });
              });
        });
    }

    /**
     * Scope: notifications within their active date window.
     */
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

    /**
     * Scope: apply trigger_days_before_due filter for a specific student.
     *
     * Notifications without a trigger window are always shown.
     * Notifications WITH a trigger window only show when the student has
     * an unpaid payment term whose due date is within that many days from today.
     *
     * FIXED: Replaced MySQL-only DATEDIFF/CURDATE() with a driver-agnostic
     * date range check that works in both MySQL (production) and SQLite (testing).
     */
    public function scopeForDueDateTrigger($query, User $user)
    {
        $today        = now()->toDateString();
        $maxLookahead = now()->addDays(90)->toDateString(); // Widest supported trigger window

        return $query->where(function ($q) use ($user, $today, $maxLookahead) {
            $q->whereNull('trigger_days_before_due')
              ->orWhere(function ($q2) use ($user, $today, $maxLookahead) {
                  $q2->whereNotNull('trigger_days_before_due')
                     ->whereExists(function ($sub) use ($user, $today, $maxLookahead) {
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
                                 'student_payment_terms.due_date <= ' . self::addDaysExpression('admin_notifications.trigger_days_before_due')
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

    /**
     * Returns a driver-agnostic SQL expression: today + N days.
     *
     * MySQL:  DATE_ADD(CURDATE(), INTERVAL <column> DAY)
     * SQLite: DATE('now', '+' || <column> || ' days')
     *
     * @param string $columnExpression  SQL column or expression holding the number of days
     */
    public static function addDaysExpression(string $columnExpression): string
    {
        $driver = \Illuminate\Support\Facades\Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return "DATE('now', '+' || {$columnExpression} || ' days')";
        }

        return "DATE_ADD(CURDATE(), INTERVAL {$columnExpression} DAY)";
    }
}