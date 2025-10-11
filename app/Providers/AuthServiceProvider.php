<?php

namespace App\Providers;

use App\Models\Collaborator;
use App\Policies\CollaboratorPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Collaborator::class => CollaboratorPolicy::class,
    ];

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
