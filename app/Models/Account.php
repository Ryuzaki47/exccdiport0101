<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = ['user_id', 'account_number', 'balance'];

    protected $casts = ['balance' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Generate a unique formatted account number
     * Format: ACC-YEAR-0001
     */
    public static function generateAccountNumber(): string
    {
        $year = now()->year;
        
        $lastAccount = self::where('account_number', 'like', "ACC-{$year}-%")
            ->lockForUpdate()
            ->orderByRaw("CAST(SUBSTRING(account_number, 10) AS UNSIGNED) DESC")
            ->first();

        if ($lastAccount) {
            $lastNumber = intval(substr($lastAccount->account_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        $newAccountNumber = "ACC-{$year}-{$newNumber}";
        
        // Double-check uniqueness with retry logic
        $attempts = 0;
        while (self::where('account_number', $newAccountNumber)->exists() && $attempts < 10) {
            $lastNumber = intval($newNumber);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $newAccountNumber = "ACC-{$year}-{$newNumber}";
            $attempts++;
        }
        
        if ($attempts >= 10) {
            throw new \Exception('Unable to generate unique account number after multiple attempts.');
        }

        return $newAccountNumber;
    }
}