<?php

namespace App\Containers\AppSection\Authentication\UI\API\Requests\Auth;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Введите email.',
            'email.email' => 'Введите корректный email.',
            'email.unique' => 'Этот email уже зарегистрирован.',
            'password.required' => 'Введите пароль.',
            'password.min' => 'Пароль должен быть не короче 8 символов.',
        ];
    }
}
