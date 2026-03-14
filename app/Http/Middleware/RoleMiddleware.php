<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Map each role to its named dashboard route.
     * Used to redirect users who land on a page they don't have access to.
     */
    private const ROLE_DASHBOARDS = [
        'admin'      => 'admin.dashboard',
        'accounting' => 'accounting.dashboard',
        'student'    => 'student.dashboard',
    ];

    /**
     * Handle an incoming request.
     *
     * If the authenticated user does not hold one of the required $roles,
     * redirect them to their own dashboard with a descriptive warning flash
     * instead of throwing a raw 403 error page.
     *
     * If the user is not authenticated at all, fall through to the standard
     * Laravel auth redirect (handled by the 'auth' middleware upstream).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Allowed roles for this route (e.g. 'admin', 'accounting')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        // Not authenticated — let the 'auth' middleware handle the redirect.
        if (! $user) {
            return redirect()->route('login');
        }

        $userRole = $user->role instanceof UserRoleEnum
            ? $user->role->value
            : (string) $user->role;

        // Role is authorized — continue to the controller.
        if (in_array($userRole, $roles, true)) {
            return $next($request);
        }

        // Role mismatch — redirect to the user's own dashboard with a flash.
        $dashboardRoute = self::ROLE_DASHBOARDS[$userRole] ?? 'dashboard';

        $requiredLabel = $this->formatRoleList($roles);

        return redirect()
            ->route($dashboardRoute)
            ->with('flash.warning', "You don't have permission to access that page. It requires {$requiredLabel} access.");
    }

    /**
     * Format a list of role slugs into a human-readable string.
     * e.g. ['admin', 'accounting'] → "Admin or Accounting"
     */
    private function formatRoleList(array $roles): string
    {
        $labels = array_map(
            fn (string $role) => match ($role) {
                'admin'      => 'Admin',
                'accounting' => 'Accounting',
                'student'    => 'Student',
                default      => ucfirst($role),
            },
            $roles
        );

        if (count($labels) === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);
        return implode(', ', $labels) . ' or ' . $last;
    }
}