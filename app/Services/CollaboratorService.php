<?php

namespace App\Services;

use App\Contracts\CollaboratorServiceContract;
use App\Models\Collaborator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CollaboratorService implements CollaboratorServiceContract
{
    /**
     * @return LengthAwarePaginator<int, Collaborator>
     */
    public function searchAndPaginateForUserId(int $userId, ?string $name = null): LengthAwarePaginator
    {
        // usando o custom query builder que criei para o model Collaborator
        return Collaborator::query()->searchAndPaginateForUserId($userId, $name);
    }

    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     cpf: string,
     *     city: string,
     *     state: string
     * } $data
     */
    public function createForUserId(int $userId, array $data): Collaborator
    {
        $this->ensureUniqueForUserId($userId, $data);

        $collaborator = new Collaborator($data);
        $collaborator->user_id = $userId;
        $collaborator->save();

        return $collaborator;
    }

    /**
     * @param  array<string, string>  $data
     */
    public function updateForUserId(int $userId, Collaborator $collaborator, array $data): Collaborator
    {
        $this->ensureCollaboratorBelongsToUserId($userId, $collaborator);
        $this->ensureUniqueForUserId($userId, $data, $collaborator->id);

        $collaborator->fill($data);
        $collaborator->save();

        return $collaborator;
    }

    public function deleteForUserId(int $userId, Collaborator $collaborator): void
    {
        $this->ensureCollaboratorBelongsToUserId($userId, $collaborator);

        $collaborator->delete();
    }

    /**
     * @param  array<string, string>  $data
     */
    private function ensureUniqueForUserId(int $userId, array $data, ?int $ignoreId = null): void
    {
        $errors = [];

        // essas regras poderiam ser uma rule customizada ou um unique no form request. Porém, ao trabalhar
        // com apps multi tenancy, tive diversos problemas em fazer conexões com o banco tenant durante a etapa da execução das form requests.
        // No fim, adotei por padrão deixar essas regras de validação nas services/VOs/Repositories, pois acho que elas ficam mais explícitas
        foreach (['email', 'cpf'] as $attribute) {
            if (! array_key_exists($attribute, $data)) {
                continue;
            }

            $value = $data[$attribute];

            if ($value === '') {
                continue;
            }

            $exists = Collaborator::query()
                ->forUserId($userId)
                ->where($attribute, $value)
                ->when($ignoreId !== null, static fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if ($exists) {
                $attributeLabel = trans("validation.attributes.{$attribute}");

                if ($attributeLabel === "validation.attributes.{$attribute}") {
                    $attributeLabel = $attribute;
                }

                $errors[$attribute] = trans('validation.unique', [
                    'attribute' => $attributeLabel,
                ]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function ensureCollaboratorBelongsToUserId(int $userId, Collaborator $collaborator): void
    {
        if ($collaborator->user_id !== $userId) {
            throw new AuthorizationException;
        }
    }
}
