<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /*
         * Mostrando a documentação da API apenas para o usuário admin
         * Gate::define('viewApiDocs', static function (?User $user): bool {
         *     return (bool) $user && $user->email === 'admin@example.com';
         * });
         *
         */
    }
}
