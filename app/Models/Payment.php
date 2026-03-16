<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a completed payment entry in the payment history table.
 *
 * IMPORTANT — No balance recalculation here.
 *
 * The previous version of this model had a static::saved() boot hook that
 * called AccountService::recalculate(). This created a double-recalculate
 * loop: StudentPaymentService already calls recalculate() after creating a
 * Payment record, so the boot hook fired a SECOND recalculate immediately
 * after, causing two identical DB writes per payment (Bug #4 effect).
 *
 * AccountService::recalculate() is now called ONLY by the service layer:
 *   - StudentPaymentService::processPayment()  (direct / staff payments)
 *   - StudentPaymentService::finalizeApprovedPayment()  (workflow approvals)
 *
 * Do NOT re-add a saved/created observer here.
 */
class Payment extends Model
{
    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING   = 'pending';
    const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'student_id',
        'student_assessment_id',
        'amount',
        'description',
        'payment_method',
        'reference_number',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class, 'student_assessment_id');
    }
}