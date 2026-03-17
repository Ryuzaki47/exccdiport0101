<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StudentAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'assessment_number',
        'year_level',
        'semester',
        'school_year',
        'tuition_fee',
        'other_fees',
        'total_assessment',
        'subjects',
        'fee_breakdown',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tuition_fee'       => 'decimal:2',
        'other_fees'        => 'decimal:2',
        'total_assessment'  => 'decimal:2',
        'subjects'          => 'array',
        'fee_breakdown'     => 'array',
    ];

    /**
     * Boot method: automatically bust the Inertia shared-data cache
     * whenever an assessment is created or updated so the sidebar/header
     * always reflects the correct year_level / semester / school_year.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::created(fn (self $a) => static::clearUserCache($a->user_id));
        static::updated(fn (self $a) => static::clearUserCache($a->user_id));
    }

    /**
     * Clear the cached latestAssessmentInfo for a given user.
     * Call this any time you create or update an assessment outside of Eloquent
     * (e.g., bulk inserts via DB::table()).
     */
    public static function clearUserCache(int $userId): void
    {
        Cache::forget("student_assessment_info:{$userId}");
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'user_id', 'user_id');
    }

    public function paymentTerms(): HasMany
    {
        return $this->hasMany(StudentPaymentTerm::class, 'student_assessment_id');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Generate a unique assessment number in the format ASS-YYYY-NNNN.
     *
     * CONTRACT: This method MUST be called inside an active DB transaction
     * (i.e. after DB::beginTransaction() has been called by the caller).
     * The SELECT ... FOR UPDATE lock prevents two concurrent requests from
     * reading the same "last number" and producing a duplicate assessment
     * number before either transaction commits.
     *
     * Correct usage (in StudentFeeController::store()):
     *
     *   DB::beginTransaction();
     *   try {
     *       $assessment = StudentAssessment::create([
     *           'assessment_number' => StudentAssessment::generateAssessmentNumber(),
     *           ...
     *       ]);
     *       DB::commit();
     *   } catch (\Exception $e) {
     *       DB::rollBack();
     *       ...
     *   }
     *
     * @throws \RuntimeException if called outside a transaction.
     */
    public static function generateAssessmentNumber(): string
    {
        // Guard: enforce the transaction contract so callers cannot accidentally
        // use this outside a transaction and produce duplicate numbers.
        if (DB::transactionLevel() === 0) {
            throw new \RuntimeException(
                'StudentAssessment::generateAssessmentNumber() must be called inside an active DB transaction.'
            );
        }

        $year = now()->year;

        // SELECT ... FOR UPDATE acquires a row-level lock on the latest record
        // for the current year. Any concurrent transaction attempting the same
        // query will block here until the first one commits or rolls back,
        // guaranteeing sequential number assignment.
        $last = self::where('assessment_number', 'like', "ASS-{$year}-%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $lastNumber = $last
            ? (int) substr($last->assessment_number, -4)
            : 0;

        return sprintf('ASS-%d-%04d', $year, $lastNumber + 1);
    }

    /**
     * Recalculate and persist the total_assessment from its components.
     */
    public function calculateTotal(): void
    {
        $this->total_assessment = $this->tuition_fee + $this->other_fees;
        $this->save();
    }
}