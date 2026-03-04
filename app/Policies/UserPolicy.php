<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine whether the user can view the user.
     * Only super admins can view other users; everyone can view own profile.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id && $user->is_active) {
            return true;
        }

        // Only super admins can view other users
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine whether the user can create users.
     * Only super admins can create new admins.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine whether the user can update the user.
     * Users can update their own profile; only super admins can update others.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile (if active)
        if ($user->id === $model->id && $user->is_active) {
            return true;
        }

        // Only super admins can update other users
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine whether the user can delete the user.
     * Hard delete is never allowed (use deactivate instead).
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete self
        if ($user->id === $model->id) {
            return false;
        }

        // Hard delete is forbidden by business rule
        return false;
    }

    /**
     * Determine whether the user can restore the user.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine whether the user can permanently delete the user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine if user can manage admin accounts
     */
    public function manageAdmins(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine if user can accept terms
     */
    public function acceptTerms(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }
}