<?php

namespace App\Api\v1\Http\Requests\Collaborator;

use App\Models\Collaborator;
use Illuminate\Foundation\Http\FormRequest;

class ImportCollaboratorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('create', Collaborator::class);
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'], // max 20MB
        ];
    }
}
