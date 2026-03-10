<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

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
     *
     * Matches:
     *   - Notifications targeted directly at this user (user_id = $user->id)
     *   - Role-based notifications for this user's role (or 'all') with no specific user_id
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
     *
     * FIX: Previous version had incorrectly nested clauses that could
     * include notifications whose start_date was in the future.
     * Both the start and end date gates must be separate where() calls.
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
     * an unpaid payment term due within that many days.
     */
    public function scopeForDueDateTrigger($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereNull('trigger_days_before_due')
              ->orWhere(function ($q2) use ($user) {
                  $q2->whereNotNull('trigger_days_before_due')
                     ->whereExists(function ($sub) use ($user) {
                         $sub->from('student_payment_terms')
                             ->join(
                                 'student_assessments',
                                 'student_assessments.id',
                                 '=',
                                 'student_payment_terms.student_assessment_id'
                             )
                             ->where('student_assessments.user_id', $user->id)
                             ->whereRaw('student_payment_terms.balance > 0')
                             ->whereNotNull('student_payment_terms.due_date')
                             ->whereRaw(
                                 'DATEDIFF(student_payment_terms.due_date, CURDATE()) BETWEEN 0 AND notifications.trigger_days_before_due'
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
}