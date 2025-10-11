<?php

namespace App\Api\v1\Http\Requests\Collaborator;

use App\Models\Collaborator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var Collaborator $collaborator */
        $collaborator = $this->route('collaborator');

        if ($user === null) {
            return false;
        }

        return $user->can('update', $collaborator);
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'cpf' => ['sometimes', 'required', 'string', 'size:11'],
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'state' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cpf')) {
            $cpfInput = $this->input('cpf');

            if (is_string($cpfInput)) {
                $sanitizedCpf = preg_replace('/\D+/', '', $cpfInput) ?? '';

                $this->merge([
                    'cpf' => $sanitizedCpf,
                ]);
            }
        }

        if ($this->has('email')) {
            $emailInput = $this->input('email');

            if (is_string($emailInput)) {
                $this->merge([
                    'email' => strtolower($emailInput),
                ]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function validatedData(): array
    {
        /** @var array<string, string> $data */
        $data = $this->validated();

        return $data;
    }
}
