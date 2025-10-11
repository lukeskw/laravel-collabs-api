<?php

namespace App\Api\v1\Http\Resources;

use App\Models\Collaborator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Collaborator */
class CollaboratorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'cpf' => (string) $this->cpf,
            'cpfFormatted' => $this->cpfFormatted,
            'city' => $this->city,
            'state' => $this->state,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
