<?php

namespace App\Api\v1\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => trans('validation.required', ['attribute' => trans('validation.attributes.email')]),
            'email.email' => trans('validation.email', ['attribute' => trans('validation.attributes.email')]),
            'password.required' => trans('validation.required', ['attribute' => trans('validation.attributes.password')]),
            'password.string' => trans('validation.string', ['attribute' => trans('validation.attributes.password')]),
        ];
    }
}
