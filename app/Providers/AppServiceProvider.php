<?php

namespace App\Providers;

use App\Policies\OwnerPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        foreach ([
            'Asset', 'AssetBucketAllocation', 'AssetValuation', 'AuditLog', 'Backup', 'Bucket',
            'CashFlow', 'DecisionJournalEntry', 'FinancialSetting', 'FxRate', 'Goal', 'ImportBatch',
            'ImportRow', 'IntegrityCheck', 'LedgerTransaction', 'Liability', 'LiabilityBalanceHistory',
            'MonthlyFinancialReview', 'RecurringCommitment', 'Snapshot', 'TransactionCategory',
            'TransactionSplit', 'AllocationPlan', 'AllocationPlanItem', 'GoldPrice',
        ] as $model) {
            Gate::policy('App\\Models\\'.$model, OwnerPolicy::class);
        }
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(3)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
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
