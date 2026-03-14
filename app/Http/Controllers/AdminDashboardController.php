<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Models\WorkflowApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        // ── Admin counts ─────────────────────────────────────────────────────
        // Use the User::admins() scope instead of repeating where('role','admin')
        // across every query. A single base query is cloned for each filter.
        $adminBase = User::admins();

        $totalAdmins    = (clone $adminBase)->count();
        $activeAdmins   = (clone $adminBase)->where('is_active', true)->count();
        $inactiveAdmins = $totalAdmins - $activeAdmins;

        // Admin breakdown by type
        $superAdmins = (clone $adminBase)->where('admin_type', User::ADMIN_TYPE_SUPER)->count();
        $managers    = (clone $adminBase)->where('admin_type', User::ADMIN_TYPE_MANAGER)->count();
        $operators   = (clone $adminBase)->where('admin_type', User::ADMIN_TYPE_OPERATOR)->count();

        // ── General user stats ────────────────────────────────────────────────
        $totalUsers    = User::count();
        $totalStudents = User::students()->count();

        // ── Pending approvals ─────────────────────────────────────────────────
        $pendingApprovals = WorkflowApproval::where('status', 'pending')->count();

        // ── Recent notifications ──────────────────────────────────────────────
        $recentNotifications = Notification::orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Notification $notif) => [
                'id'         => $notif->id,
                'title'      => $notif->title,
                'targetRole' => $notif->target_role,
                'startDate'  => $notif->start_date,
                'endDate'    => $notif->end_date,
                'createdAt'  => $notif->created_at,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalAdmins'         => $totalAdmins,
                'activeAdmins'        => $activeAdmins,
                'inactiveAdmins'      => $inactiveAdmins,
                'superAdmins'         => $superAdmins,
                'managers'            => $managers,
                'operators'           => $operators,
                'totalUsers'          => $totalUsers,
                'totalStudents'       => $totalStudents,
                'pendingApprovals'    => $pendingApprovals,
                'recentActivities'    => [],   // placeholder — wire up activity log here
                'recentNotifications' => $recentNotifications,
                'systemHealth'        => [
                    'status'               => 'operational',
                    'databaseStatus'       => 'operational',
                    'apiStatus'            => 'operational',
                    'authenticationStatus' => 'operational',
                ],
            ],
        ]);
    }
}