<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // project settings that I like to use.
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Model::shouldBeStrict(
            app()->isLocal()
        );

        // https://laravel.com/docs/12.x/eloquent-relationships#automatic-eager-loading
        Model::automaticallyEagerLoadRelationships();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
