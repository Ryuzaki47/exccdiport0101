<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
use App\Models\StudentAssessment;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
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

        return [
            ...parent::share($request),
            'name'                 => config('app.name'),
            'quote'                => ['message' => trim($message), 'author' => trim($author)],
            'auth'                 => ['user' => $request->user()],
            'latestAssessmentInfo' => $this->resolveLatestAssessmentInfo($request),
            'sidebarOpen'          => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrf_token'           => csrf_token(),
            // Flash message for role-based redirects (set by RoleMiddleware)
            'flash'                => [
                'error'   => $request->session()->pull('flash.error'),
                'warning' => $request->session()->pull('flash.warning'),
                'success' => $request->session()->pull('flash.success'),
                'info'    => $request->session()->pull('flash.info'),
            ],
        ];
    }

    /**
     * Resolve the latest active assessment info for student users.
     *
     * Cached per-user for 5 minutes to avoid a DB hit on every Inertia
     * request. Cache is invalidated by StudentAssessment::clearUserCache()
     * whenever an assessment is created or its status changes.
     */
    private function resolveLatestAssessmentInfo(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRoleEnum::STUDENT) {
            return null;
        }

        $cacheKey = "student_assessment_info:{$user->id}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            $assessment = StudentAssessment::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first(['year_level', 'semester', 'school_year']);

            if (! $assessment) {
                return null;
            }

            return [
                'year_level'  => $assessment->year_level,
                'semester'    => $assessment->semester,
                'school_year' => $assessment->school_year,
            ];
        });
    }
}