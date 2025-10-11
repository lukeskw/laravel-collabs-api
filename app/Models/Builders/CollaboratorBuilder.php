<?php

namespace App\Models\Builders;

use App\Models\Collaborator;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of Collaborator
 *
 * @extends Builder<TModel>
 */
class CollaboratorBuilder extends Builder
{
    public function forUser(User $user): static
    {
        return $this->where('user_id', $user->id);
    }

    public function forUserId(int $userId): static
    {
        return $this->where('user_id', $userId);
    }

    /** @return LengthAwarePaginator<int, TModel> */
    public function searchAndPaginateForUserId(int $userId, ?string $name = null): LengthAwarePaginator
    {
        return $this->forUserId($userId)
            ->when($name !== null, static function (CollaboratorBuilder $query) use ($name): CollaboratorBuilder {
                $query->where('name', 'ILIKE', "%{$name}%");

                return $query;
            })
            ->latest()
            ->paginate();
    }
}
