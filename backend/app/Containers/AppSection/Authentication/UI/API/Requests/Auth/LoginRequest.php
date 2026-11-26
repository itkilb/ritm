<?php

namespace App\Containers\AppSection\Authentication\UI\API\Requests\Auth;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class LoginRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Введите email.',
            'email.email' => 'Введите корректный email.',
            'password.required' => 'Введите пароль.',
        ];
    }
}
