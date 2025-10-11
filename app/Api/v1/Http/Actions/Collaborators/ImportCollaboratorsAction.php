<?php

namespace App\Api\v1\Http\Actions\Collaborators;

use App\Api\v1\Http\Requests\Collaborator\ImportCollaboratorsRequest;
use App\Jobs\ProcessCollaboratorsImport;
use App\Models\Collaborator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

// Quis mostrar outros padrões que a comunidade Laravel usa bastante, como Actions
class ImportCollaboratorsAction
{
    // use AuthorizesRequests;

    public function __invoke(ImportCollaboratorsRequest $request): JsonResponse
    {
        // usando a Facade Gate ao invés do trait AuthorizesRequests para variar um pouco
        // $this->authorize('create', Collaborator::class);
        Gate::authorize('create', Collaborator::class);

        $uploadedFile = $request->file('file');

        $disk = config('filesystems.default', 'local');

        if (! is_string($disk) || $disk === '') {
            throw ValidationException::withMessages([
                'file' => trans('validation.invalid_storage_disk'),
            ]);
        }

        $path = $uploadedFile->store('imports', $disk);

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => trans('validation.import_store_failed'),
            ]);
        }

        ProcessCollaboratorsImport::dispatch($this->authenticatedUserId(), $path, $disk);

        return response()->json([
            'message' => trans('general.import_started'),
        ], Response::HTTP_ACCEPTED);
    }

    private function authenticatedUserId(): int
    {
        $userId = Auth::id();

        if ($userId === null) {
            throw new AuthorizationException;
        }

        return (int) $userId;
    }
}
