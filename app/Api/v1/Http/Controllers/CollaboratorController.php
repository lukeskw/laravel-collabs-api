<?php

namespace App\Api\v1\Http\Controllers;

use App\Api\v1\Http\Requests\Collaborator\StoreCollaboratorRequest;
use App\Api\v1\Http\Requests\Collaborator\UpdateCollaboratorRequest;
use App\Api\v1\Http\Resources\CollaboratorResource;
use App\Contracts\CollaboratorServiceContract;
use App\Models\Collaborator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CollaboratorController extends Controller
{
    // adicionando caches em constantes para facilitar ajustes futuros
    private const COLLABORATOR_CACHE_REFRESH_AFTER_SECONDS = 60;

    private const COLLABORATOR_CACHE_EXPIRES_AFTER_SECONDS = 600;

    // usando o cosntructor property promotion do PHP 8 e DI do Service Container do Laravel (uma das minhas features favoritas do framework)
    // https://laravel.com/docs/12.x/container#automatic-injection
    public function __construct(
        private readonly CollaboratorServiceContract $collaboratorService
    ) {}

    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', Collaborator::class);

        $userId = $this->authenticatedUserId();
        $search = $this->searchTerm($request);
        $cacheKey = sprintf(
            'users:%d:collaborators:search:%s',
            $userId,
            md5($search ?? '_all_')
        );

        // usando a nova API de SWR do laravel 12. Referência: https://laravel.com/docs/12.x/cache#swr
        $collaborators = Cache::flexible($cacheKey, [
            self::COLLABORATOR_CACHE_REFRESH_AFTER_SECONDS,
            self::COLLABORATOR_CACHE_EXPIRES_AFTER_SECONDS,
        ], function () use ($userId, $search) {
            return $this->collaboratorService->searchAndPaginateForUserId($userId, $search);
        });

        return CollaboratorResource::collection($collaborators);
    }

    public function store(StoreCollaboratorRequest $request): JsonResponse
    {
        $this->authorize('create', Collaborator::class);

        $userId = $this->authenticatedUserId();

        $collaborator = $this->collaboratorService->createForUserId($userId, $request->validatedData());

        return CollaboratorResource::make($collaborator)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Collaborator $collaborator): CollaboratorResource
    {
        $this->authorize('view', $collaborator);

        return CollaboratorResource::make($collaborator);
    }

    public function update(UpdateCollaboratorRequest $request, Collaborator $collaborator): CollaboratorResource
    {
        $this->authorize('update', $collaborator);

        $userId = $this->authenticatedUserId();

        $updatedCollaborator = $this->collaboratorService->updateForUserId($userId, $collaborator, $request->validatedData());

        return CollaboratorResource::make($updatedCollaborator);
    }

    public function destroy(Collaborator $collaborator): Response
    {
        $this->authorize('delete', $collaborator);

        $userId = $this->authenticatedUserId();

        $this->collaboratorService->deleteForUserId($userId, $collaborator);

        return response()->noContent();
    }

    private function authenticatedUserId(): int
    {
        $userId = Auth::id();

        if ($userId === null) {
            throw new AuthorizationException;
        }

        return (int) $userId;
    }

    /*
    *   Eu poderia ter usado alguma lib para lidar com filtros, ordenações e afins, mas como o projeto é simples, preferi fazer na mão mesmo.
    *   Das implementações que já usei:
    *   spatie/laravel-query-builde https://github.com/spatie/laravel-query-builderr,
    *   laravel-filter-querystring https://github.com/mehradsadeghi/laravel-filter-querystring,
    *   implementação própria usando Laravel Pipelines https://laravel.com/docs/12.x/helpers#pipeline
    */
    private function searchTerm(Request $request): ?string
    {
        if (! $request->filled('search')) {
            return null;
        }

        $value = trim($request->string('search')->toString());

        return $value === '' ? null : $value;
    }
}
