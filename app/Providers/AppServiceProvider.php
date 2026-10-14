<?php

namespace App\Providers;

use App\Models\User;
use App\View\LayoutComposer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        // Admin accounts can do everything; everyone else goes through role permissions.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        Paginator::defaultView('partials.pagination');

        View::composer('layouts.app', LayoutComposer::class);

        // Translations: English text is the key; each module keeps its own lang/json/<module>/zh_CN.json.
        foreach (glob(lang_path('json/*'), GLOB_ONLYDIR) ?: [] as $dir) {
            $this->app['translator']->addJsonPath($dir);
        }
    }
}
