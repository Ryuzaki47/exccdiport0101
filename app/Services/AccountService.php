<?php

namespace App\Services;

use App\Models\User;

/**
 * Single authoritative writer for student account balances.
 *
 * INVARIANT: `accounts.balance` is the ONE source of truth.
 *   - Every balance read goes through `$user->account->balance`.
 *   - This service is the ONLY writer of that column.
 *   - `students.total_balance` has been removed (migration 2026_03_17_000001).
 *
 * Call `recalculate()` after any event that changes the financial position:
 *   - After StudentPaymentService::processPayment / finalizeApprovedPayment
 *   - After StudentFeeController::store / storePayment / update
 */
class AccountService
{
    /**
     * Recompute and persist the authoritative balance for a user.
     *
     * Balance = SUM(charge transactions) - SUM(paid payment transactions)
     *
     * Positive  → student owes money.
     * Zero/negative → fully paid (or over-paid / credit).
     *
     * @param  User|null  $user  Null-safe: no-op when null.
     */
    public static function recalculate(?User $user): void
    {
        if (! $user) {
            return;
        }

        $charges = (float) $user->transactions()
            ->where('kind', 'charge')
            ->sum('amount');

        $payments = (float) $user->transactions()
            ->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');

        $balance = round($charges - $payments, 2);

        // ── Single source of truth: accounts.balance ──────────────────────────
        $account = $user->account ?? $user->account()->create(['balance' => 0]);
        $account->update(['balance' => $balance]);

        // NOTE: students.total_balance has been REMOVED (migration 2026_03_17_000001).
        // There is no second write here. accounts.balance is the only balance column.

        // ── Auto-promote when fully settled ───────────────────────────────────
        if ($user->student && $charges > 0 && $balance <= 0) {
            self::promoteStudent($user);
        }
    }

    /**
     * Promote student to next year level (or graduate) when balance reaches zero.
     *
     * Reads/writes users.year_level and users.status exclusively.
     * students.year_level / students.status were dropped in migration
     * 2026_03_16_000000_remove_duplicate_columns_from_students_table.
     */
    protected static function promoteStudent(User $user): void
    {
        $yearLevels   = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $currentIndex = array_search($user->year_level, $yearLevels, strict: true);

        if ($currentIndex === false) {
            return;
        }

        if ($currentIndex < count($yearLevels) - 1) {
            $user->update(['year_level' => $yearLevels[$currentIndex + 1]]);
        } else {
            $user->update(['status' => User::STATUS_GRADUATED]);
        }
    }
}