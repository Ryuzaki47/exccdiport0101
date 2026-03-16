<?php

namespace App\Services;

use App\Models\User;

class AccountService
{
    /**
     * Recalculate a user's balance based on transactions.
     *
     * Queries `transactions` for the authoritative balance and writes the
     * result to BOTH `accounts.balance` AND `students.total_balance` so any
     * legacy code reading either column stays consistent.
     */
    public static function recalculate(?User $user): void
    {
        // If no user is provided, safely exit (prevents seeding crashes)
        if (! $user) {
            return;
        }

        $charges = $user->transactions()
            ->where('kind', 'charge')
            ->sum('amount');

        $payments = $user->transactions()
            ->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');

        $balance = round((float) $charges - (float) $payments, 2);

        // ── Ensure account record exists and update it ────────────────────────
        $account = $user->account ?? $user->account()->create(['balance' => 0]);
        $account->update(['balance' => $balance]);

        // ── Mirror balance on the students row (kept for legacy reads) ────────
        if ($user->student) {
            $user->student->update(['total_balance' => $balance]);
        }

        // ── Auto-promote only when the account is fully settled ───────────────
        // Condition: charges exist AND balance is <= 0 (includes credit balance)
        if ($user->student && (float) $charges > 0 && $balance <= 0) {
            self::promoteStudent($user);
        }
    }

    /**
     * Promote student to next year level when balance reaches zero.
     *
     * FIX (Bug #2): Previously this method read and wrote `students.year_level`
     * and `students.status`, both of which were dropped in migration
     * 2026_03_16_000000_remove_duplicate_columns_from_students_table.
     *
     * All personal/academic data now lives in the `users` table only.
     * This method reads `users.year_level` and writes to `users.year_level` /
     * `users.status` accordingly.
     */
    protected static function promoteStudent(User $user): void
    {
        $yearLevels = [
            '1st Year',
            '2nd Year',
            '3rd Year',
            '4th Year',
        ];

        // Read year_level from users table (NOT students table)
        $currentIndex = array_search($user->year_level, $yearLevels);

        if ($currentIndex === false) {
            // Unknown year level — skip promotion silently
            return;
        }

        if ($currentIndex < count($yearLevels) - 1) {
            // Promote to next year — write to users table
            $user->update([
                'year_level' => $yearLevels[$currentIndex + 1],
            ]);
        } else {
            // Final year completed — graduate — write to users table
            $user->update([
                'status' => User::STATUS_GRADUATED,
            ]);
        }
    }
}