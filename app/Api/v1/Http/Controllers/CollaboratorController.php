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

/**
 * @OA\Tag(
 *   name="Collaborators",
 *   description="Manage collaborators for the authenticated user"
 * )
 *
 * @OA\Schema(
 *   schema="CollaboratorData",
 *   type="object",
 *   required={"id","name","email","cpf","cpfFormatted","city","state","createdAt","updatedAt"},
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Maria Souza"),
 *   @OA\Property(property="email", type="string", format="email", example="maria@example.com"),
 *   @OA\Property(property="cpf", type="string", example="12345678901"),
 *   @OA\Property(property="cpfFormatted", type="string", example="123.456.789-01"),
 *   @OA\Property(property="city", type="string", example="São Paulo"),
 *   @OA\Property(property="state", type="string", example="SP"),
 *   @OA\Property(property="createdAt", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
 *   @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-01-02T12:00:00Z")
 * )
 *
 * @OA\Schema(
 *   schema="CollaboratorResource",
 *   type="object",
 *   @OA\Property(property="data", ref="#/components/schemas/CollaboratorData")
 * )
 *
 * @OA\Schema(
 *   schema="CollaboratorCollection",
 *   type="object",
 *   @OA\Property(
 *     property="data",
 *     type="array",
 *     @OA\Items(ref="#/components/schemas/CollaboratorData")
 *   ),
 *   @OA\Property(
 *     property="links",
 *     type="object",
 *     @OA\Property(property="first", type="string", nullable=true, example="https://api.example.com/api/v1/collaborators?page=1"),
 *     @OA\Property(property="last", type="string", nullable=true, example="https://api.example.com/api/v1/collaborators?page=5"),
 *     @OA\Property(property="prev", type="string", nullable=true, example=null),
 *     @OA\Property(property="next", type="string", nullable=true, example="https://api.example.com/api/v1/collaborators?page=2")
 *   ),
 *   @OA\Property(
 *     property="meta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="from", type="integer", nullable=true, example=1),
 *     @OA\Property(property="last_page", type="integer", example=5),
 *     @OA\Property(
 *       property="links",
 *       type="array",
 *       @OA\Items(
 *         type="object",
 *         @OA\Property(property="url", type="string", nullable=true),
 *         @OA\Property(property="label", type="string"),
 *         @OA\Property(property="active", type="boolean")
 *       )
 *     ),
 *     @OA\Property(property="path", type="string", example="https://api.example.com/api/v1/collaborators"),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="to", type="integer", nullable=true, example=15),
 *     @OA\Property(property="total", type="integer", example=57)
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="CollaboratorStoreRequest",
 *   type="object",
 *   required={"name","email","cpf","city","state"},
 *   @OA\Property(property="name", type="string", example="Maria Souza"),
 *   @OA\Property(property="email", type="string", format="email", example="maria@example.com"),
 *   @OA\Property(property="cpf", type="string", example="12345678901"),
 *   @OA\Property(property="city", type="string", example="São Paulo"),
 *   @OA\Property(property="state", type="string", example="SP")
 * )
 *
 * @OA\Schema(
 *   schema="CollaboratorUpdateRequest",
 *   type="object",
 *   @OA\Property(property="name", type="string", example="Maria Souza"),
 *   @OA\Property(property="email", type="string", format="email", example="maria@example.com"),
 *   @OA\Property(property="cpf", type="string", example="12345678901"),
 *   @OA\Property(property="city", type="string", example="São Paulo"),
 *   @OA\Property(property="state", type="string", example="SP")
 * )
 */
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

    /**
     * List collaborators
     *
     * @OA\Get(
     *   path="/api/v1/collaborators",
     *   tags={"Collaborators"},
     *   summary="List collaborators for the authenticated user",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(
     *     name="search",
     *     in="query",
     *     description="Filter collaborators by name, email or CPF",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Paginated list of collaborators",
     *     @OA\JsonContent(ref="#/components/schemas/CollaboratorCollection")
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden")
     * )
     */
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

    /**
     * Create a collaborator
     *
     * @OA\Post(
     *   path="/api/v1/collaborators",
     *   tags={"Collaborators"},
     *   summary="Create a collaborator",
     *   security={{"bearerAuth": {}}},
     *   requestBody=@OA\RequestBody(
     *     request="StoreCollaboratorRequest",
     *     required=true,
     *     @OA\JsonContent(ref="#/components/schemas/CollaboratorStoreRequest")
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Collaborator created",
     *     @OA\JsonContent(ref="#/components/schemas/CollaboratorResource")
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="errors",
     *         type="object",
     *         additionalProperties=@OA\Schema(schema="ValidationErrorsArrayOfStringsForStore", type="array", @OA\Items(type="string"))
     *       )
     *     )
     *   )
     * )
     */
    public function store(StoreCollaboratorRequest $request): JsonResponse
    {
        $this->authorize('create', Collaborator::class);

        $userId = $this->authenticatedUserId();

        $collaborator = $this->collaboratorService->createForUserId($userId, $request->validatedData());

        return CollaboratorResource::make($collaborator)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a collaborator
     *
     * @OA\Get(
     *   path="/api/v1/collaborators/{collaborator}",
     *   tags={"Collaborators"},
     *   summary="Retrieve a single collaborator",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(
     *     name="collaborator",
     *     in="path",
     *     required=true,
     *     description="Collaborator identifier",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Collaborator details",
     *     @OA\JsonContent(ref="#/components/schemas/CollaboratorResource")
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Collaborator $collaborator): CollaboratorResource
    {
        $this->authorize('view', $collaborator);

        return CollaboratorResource::make($collaborator);
    }

    /**
     * Update a collaborator
     *
     * @OA\Put(
     *   path="/api/v1/collaborators/{collaborator}",
     *   tags={"Collaborators"},
     *   summary="Replace collaborator data",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(
     *     name="collaborator",
     *     in="path",
     *     required=true,
     *     description="Collaborator identifier",
     *     @OA\Schema(type="integer")
     *   ),
     *   requestBody=@OA\RequestBody(
     *     request="UpdateCollaboratorRequest",
     *     required=false,
     *     @OA\JsonContent(ref="#/components/schemas/CollaboratorUpdateRequest")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Collaborator updated",
     *     @OA\JsonContent(ref="#/components/schemas/CollaboratorResource")
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Not found"),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="errors",
     *         type="object",
     *         additionalProperties=@OA\Schema(schema="ValidationErrorsArrayOfStringsForUpdate", type="array", @OA\Items(type="string"))
     *       )
     *     )
     *   )
     * )
     *
     * @OA\Patch(
     *   path="/api/v1/collaborators/{collaborator}",
     *   tags={"Collaborators"},
     *   summary="Partially update collaborator data",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(
     *     name="collaborator",
     *     in="path",
     *     required=true,
     *     description="Collaborator identifier",
     *     @OA\Schema(type="integer")
     *   ),
     *   requestBody=@OA\RequestBody(
     *     request="PartialUpdateCollaboratorRequest",
     *     required=false,
     *     @OA\JsonContent(ref="#/components/schemas/CollaboratorUpdateRequest")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Collaborator updated",
     *     @OA\JsonContent(ref="#/components/schemas/CollaboratorResource")
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Not found"),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="errors",
     *         type="object",
     *         additionalProperties=@OA\Schema(schema="ValidationErrorsArrayOfStringsForPatch", type="array", @OA\Items(type="string"))
     *       )
     *     )
     *   )
     * )
     */
    public function update(UpdateCollaboratorRequest $request, Collaborator $collaborator): CollaboratorResource
    {
        $this->authorize('update', $collaborator);

        $userId = $this->authenticatedUserId();

        $updatedCollaborator = $this->collaboratorService->updateForUserId($userId, $collaborator, $request->validatedData());

        return CollaboratorResource::make($updatedCollaborator);
    }

    /**
     * Delete a collaborator
     *
     * @OA\Delete(
     *   path="/api/v1/collaborators/{collaborator}",
     *   tags={"Collaborators"},
     *   summary="Delete a collaborator",
     *   security={{"bearerAuth": {}}},
     *   @OA\Parameter(
     *     name="collaborator",
     *     in="path",
     *     required=true,
     *     description="Collaborator identifier",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=204, description="Collaborator deleted"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
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
