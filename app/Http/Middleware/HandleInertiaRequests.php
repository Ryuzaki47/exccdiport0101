<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Ensure session is started to generate CSRF token
        $token = csrf_token();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            // ── Expose latest assessment data for the authenticated student ──
            // Profile.vue reads auth.user.year_level which is the raw DB value and
            // may be stale. Provide the assessment-derived year_level as a fallback
            // so the Profile always shows the accurate academic year level.
            'latestAssessmentInfo' => (function () use ($request) {
                $user = $request->user();
                if (!$user || !$user->role || $user->role->value !== 'student') {
                    return null;
                }
                $assessment = \App\Models\StudentAssessment::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->latest()
                    ->first();
                if (!$assessment) return null;
                return [
                    'year_level'  => $assessment->year_level,
                    'semester'    => $assessment->semester,
                    'school_year' => $assessment->school_year,
                ];
            })(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrf_token' => $token,
        ];
    }
}