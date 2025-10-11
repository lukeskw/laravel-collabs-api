<?php

namespace App\Contracts;

use App\Models\Collaborator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CollaboratorServiceContract
{
    /**
     * @return LengthAwarePaginator<int, Collaborator>
     */
    public function searchAndPaginateForUserId(int $userId, ?string $name = null): LengthAwarePaginator;

    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     cpf: string,
     *     city: string,
     *     state: string
     * } $data
     */
    public function createForUserId(int $userId, array $data): Collaborator;

    /**
     * @param  array<string, string>  $data
     */
    public function updateForUserId(int $userId, Collaborator $collaborator, array $data): Collaborator;

    public function deleteForUserId(int $userId, Collaborator $collaborator): void;
}
