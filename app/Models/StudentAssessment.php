<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

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
     * Generate a unique assessment number (e.g. ASS-2026-0001).
     */
    public static function generateAssessmentNumber(): string
    {
        $year = now()->year;
        $lastAssessment = self::where('assessment_number', 'like', "ASS-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = $lastAssessment
            ? intval(substr($lastAssessment->assessment_number, -4))
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