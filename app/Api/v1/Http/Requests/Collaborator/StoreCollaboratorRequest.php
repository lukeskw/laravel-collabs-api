<?php

namespace App\Api\v1\Http\Requests\Collaborator;

use App\Models\Collaborator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCollaboratorRequest extends FormRequest
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
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'cpf' => ['required', 'string', 'size:11'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $cpfInput = $this->input('cpf');
        $emailInput = $this->input('email');

        if (is_string($cpfInput)) {
            $sanitizedCpf = preg_replace('/\D+/', '', $cpfInput) ?? '';

            $this->merge([
                'cpf' => $sanitizedCpf,
            ]);
        }

        if (is_string($emailInput)) {
            $this->merge([
                'email' => strtolower($emailInput),
            ]);
        }
    }

    /**
     * @return array{
     *     name: string,
     *     email: string,
     *     cpf: string,
     *     city: string,
     *     state: string
     * }
     */
    public function validatedData(): array
    {
        /** @var array{
         *     name: string,
         *     email: string,
         *     cpf: string,
         *     city: string,
         *     state: string
         * } $data
         */
        $data = $this->validated();

        return $data;
    }
}
