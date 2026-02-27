<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    // ============================================
    // FILLABLE FIELDS (Combined from both models)
    // ============================================
    protected $fillable = [
        // Original fields from your existing model
        'user_id',
        'student_id',
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'course',
        'year_level',
        'birthday',
        'phone',
        'address',
        'total_balance',
        
        // New workflow-related fields
        'student_number',
        'date_of_birth',
        'enrollment_status',
        'enrollment_date',
        'metadata',
    ];

    // ============================================
    // CASTS
    // ============================================
    protected $casts = [
        'birthday' => 'date',
        'date_of_birth' => 'date',
        'enrollment_date' => 'date',
        'total_balance' => 'decimal:2',
        'metadata' => 'array', // For storing extra JSON data
    ];

    // ============================================
    // RELATIONSHIPS - EXISTING (Your original code)
    // ============================================
    
    /**
     * Student belongs to a User account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Student has many payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Student has many transactions via the linked user
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    /**
     * Student has one account
     */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }

    // ============================================
    // RELATIONSHIPS - NEW (Workflow integration)
    // ============================================
    
    /**
     * Student can have multiple workflow instances
     * (enrollment workflows, academic workflows, etc.)
     */
    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(WorkflowInstance::class, 'workflowable');
    }

    /**
     * Student has many assessments
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'user_id', 'user_id');
    }

    /**
     * Student can have accounting transactions
     * (invoices, payments, refunds linked to this student)
     */
    public function accountingTransactions(): MorphMany
    {
        return $this->morphMany(AccountingTransaction::class, 'transactionable');
    }

    // ============================================
    // ACCESSORS & COMPUTED ATTRIBUTES
    // ============================================
    
    /**
     * Get full name of student
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_initial ? $this->middle_initial . '.' : null,
            $this->last_name,
        ]);
        
        return implode(' ', $parts);
    }

    /**
     * Calculate remaining balance (from your original model)
     */
    public function getRemainingBalanceAttribute()
    {
        $charges = $this->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $this->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        return round(max(0, (float)$charges - (float)$payments), 2);
    }

    // ============================================
    // QUERY SCOPES
    // ============================================
    
    /**
     * Scope to get only active students
     */
    public function scopeActive($query)
    {
        return $query->where('enrollment_status', 'active');
    }

    /**
     * Scope to get pending enrollment students
     */
    public function scopePending($query)
    {
        return $query->where('enrollment_status', 'pending');
    }

    /**
     * Scope to filter by course
     */
    public function scopeOfCourse($query, string $course)
    {
        return $query->where('course', $course);
    }

    /**
     * Scope to filter by year level
     */
    public function scopeOfYearLevel($query, string $yearLevel)
    {
        return $query->where('year_level', $yearLevel);
    }
}