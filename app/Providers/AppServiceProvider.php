<?php

namespace App\Providers;

use App\Enums\MenuAction;
use App\Models\School;
use App\Models\User;
use App\Support\Access\MenuAccess;
use App\Support\Navigation\SidebarNavigation;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
        $this->app->scoped(MenuAccess::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::before(function (User $user): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });

        // Views: @can('menu', ['classes', 'create']) — same rules as the menu middleware.
        Gate::define('menu', fn (User $user, string $menuKey, string $action = 'view'): bool => app(MenuAccess::class)
            ->allows($user, $menuKey, MenuAction::from($action)));

        View::composer('layouts.dashboard', function (ViewContract $view): void {
            $user = request()->user();

            $navigation = $user instanceof User
                ? app(SidebarNavigation::class)->for($user)
                : collect();

            $view->with('databaseNavigation', $navigation);

            // Top bar school switcher: the school the Super Admin is working in, if any.
            $activeSchoolId = $user instanceof User && $user->isSuperAdmin()
                ? request()->session()->get('active_school_id')
                : null;

            $view->with('activeSchool', is_numeric($activeSchoolId)
                ? School::query()->select(['id', 'name', 'code'])->find((int) $activeSchoolId)
                : null);
        });

        $this->configureDefaults();
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
