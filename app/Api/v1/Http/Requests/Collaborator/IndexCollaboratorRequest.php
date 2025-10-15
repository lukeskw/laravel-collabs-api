<?php

namespace App\Api\v1\Http\Requests\Collaborator;

use App\Models\Collaborator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('viewAny', Collaborator::class);
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $searchInput = $this->input('search');

        if (is_string($searchInput)) {
            $trimmed = trim($searchInput);
            $this->merge([
                'search' => $trimmed === '' ? null : $trimmed,
            ]);
        }
    }
}
