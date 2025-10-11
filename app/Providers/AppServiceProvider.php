<?php

namespace App\Providers;

use App\Contracts\CollaboratorServiceContract;
use App\Contracts\CollaboratorsImporterContract;
use App\Services\CollaboratorsCsvImporter;
use App\Services\CollaboratorService;
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
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        Model::shouldBeStrict(app()->isLocal());

        // https://laravel.com/docs/12.x/eloquent-relationships#automatic-eager-loading
        Model::automaticallyEagerLoadRelationships();

        // service binds. Aqui podemos trocar a implementação de serviços facilmente,
        // gosto muito dessa abordagem de programar para interfaces e deixar o container resolver pra mim.
        $this->app->bind(CollaboratorServiceContract::class, CollaboratorService::class);
        $this->app->bind(CollaboratorsImporterContract::class, CollaboratorsCsvImporter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
