<?php

namespace App\Providers;

use App\Models\CertificateReview;
use App\Models\IndustryEvaluation;
use App\Models\InternshipLeaveRequest;
use App\Models\InterviewNote;
use App\Models\InterviewSession;
use App\Models\LogbookReview;
use App\Models\PracticumAssessment;
use App\Models\PracticumSubmission;
use App\Observers\VokasimActivityObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        foreach ([LogbookReview::class, CertificateReview::class, InternshipLeaveRequest::class, IndustryEvaluation::class, PracticumSubmission::class, PracticumAssessment::class, InterviewSession::class, InterviewNote::class] as $model) {
            $model::observe(VokasimActivityObserver::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
