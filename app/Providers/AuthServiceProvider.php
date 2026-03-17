<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Notification;
use App\Models\StudentPaymentTerm;
use App\Models\Student;
use App\Policies\UserPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\StudentFeePolicy;
use App\Policies\StudentPaymentTermPolicy;
use App\Models\WorkflowApproval;
use App\Policies\WorkflowApprovalPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Notification::class => NotificationPolicy::class,
        WorkflowApproval::class => WorkflowApprovalPolicy::class,
        StudentPaymentTerm::class => StudentPaymentTermPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // ============================================================
        // ROUTE MODEL BINDING — Include Soft-Deleted Students
        // ============================================================
        // 
        // The Student model uses SoftDeletes. Routes that access
        // archived (graduated, dropped, inactive) students must
        // explicitly include soft-deleted records via withTrashed().
        //
        Route::bind('student', function ($value) {
            return Student::withTrashed()->findOrFail($value);
        });
    }
}